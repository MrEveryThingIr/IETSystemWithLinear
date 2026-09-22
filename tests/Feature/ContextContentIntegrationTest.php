<?php

namespace Tests\Feature;

use App\Actions\Contexts\CreateContextContent;
use App\Actions\Contexts\CreateContextContentDefinition;
use App\Actions\Contexts\EnsureAdmissionContext;
use App\Actions\Contexts\EnsurePersonalContext;
use App\Actions\Groups\ActivateSpaceContentDefinition;
use App\Actions\Groups\AddSpaceContentAnnotation;
use App\Actions\Groups\AttachAssetToSpaceContent;
use App\Actions\Groups\CreateGroup;
use App\Actions\Groups\PublishSpaceContent;
use App\Models\Actor;
use App\Models\Admission;
use App\Models\Context;
use App\Models\SpaceContentDefinition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContextContentIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_personal_context_supports_content_without_a_group_and_denies_outsiders(): void
    {
        $owner = Actor::factory()->create();
        $outsider = Actor::factory()->create();
        $context = app(EnsurePersonalContext::class)->execute($owner->user);

        $definition = $this->activeNoteDefinition($context, $owner);
        $content = app(CreateContextContent::class)->execute(
            $context,
            $definition,
            $owner->user,
            'Private note',
            ['body' => 'Personal context body'],
        );

        $this->assertNull($content->group_space_id);
        $this->assertSame($context->id, $content->context_id);
        $this->assertTrue(Gate::forUser($owner->user)->allows('update', $content));
        $this->assertFalse(Gate::forUser($outsider->user)->allows('view', $content));

        app(PublishSpaceContent::class)->execute($content, $owner->user);

        $this->actingAs($owner->user)
            ->get(route('contexts.contents.show', [$context, $content]))
            ->assertOk()
            ->assertSee('Private note');

        $this->actingAs($outsider->user)
            ->get(route('contexts.contents.show', [$context, $content]))
            ->assertForbidden();
    }

    public function test_personal_entry_route_lazily_provisions_one_context(): void
    {
        $owner = Actor::factory()->create();

        $response = $this->actingAs($owner->user)->get(route('contexts.personal'));

        $context = app(EnsurePersonalContext::class)->execute($owner->user);
        $response->assertRedirect(route('contexts.contents.index', $context));
        $this->assertDatabaseCount('personal_contexts', 1);
    }

    public function test_admission_candidate_and_reviewer_collaborate_before_membership_without_group_authority(): void
    {
        $reviewer = Actor::factory()->create();
        $candidate = Actor::factory()->create();
        $outsider = Actor::factory()->create();
        $group = app(CreateGroup::class)->execute($reviewer, 'Context admission group', null);
        $space = $group->spaces()->sole();

        $admission = Admission::factory()->create([
            'group_id' => $group->id,
            'candidate_actor_id' => $candidate->id,
            'status' => 'under_review',
        ]);

        $context = app(EnsureAdmissionContext::class)->execute($admission, $reviewer->user);
        $definition = $this->activeNoteDefinition($context, $reviewer);

        $content = app(CreateContextContent::class)->execute(
            $context,
            $definition,
            $candidate->user,
            'Candidate evidence note',
            ['body' => 'Shared before membership'],
        );

        $this->assertDatabaseMissing('group_memberships', [
            'group_id' => $group->id,
            'actor_id' => $candidate->id,
        ]);
        $this->assertFalse(Gate::forUser($candidate->user)->allows('view', $space));
        $this->assertTrue(Gate::forUser($candidate->user)->allows('update', $content));
        $this->assertTrue(Gate::forUser($reviewer->user)->allows('update', $content));
        $this->assertFalse(Gate::forUser($outsider->user)->allows('view', $content));

        app(PublishSpaceContent::class)->execute($content, $candidate->user);

        $this->actingAs($reviewer->user)
            ->get(route('admissions.context.contents', $admission))
            ->assertRedirect(route('contexts.contents.index', $context));

        $this->actingAs($candidate->user)
            ->get(route('contexts.contents.show', [$context, $content]))
            ->assertOk()
            ->assertSee('Candidate evidence note');
    }

    public function test_terminal_admission_content_is_read_only(): void
    {
        $reviewer = Actor::factory()->create();
        $candidate = Actor::factory()->create();
        $group = app(CreateGroup::class)->execute($reviewer, 'Terminal context group', null);
        $admission = Admission::factory()->cancelled()->create([
            'group_id' => $group->id,
            'candidate_actor_id' => $candidate->id,
        ]);

        $context = app(EnsureAdmissionContext::class)->execute($admission, $candidate->user);

        $this->assertTrue(Gate::forUser($candidate->user)->allows('view', $context));
        $this->assertFalse(Gate::forUser($candidate->user)->allows('createContent', $context));
        $this->assertFalse(Gate::forUser($reviewer->user)->allows('manageDefinitions', $context));
    }

    public function test_context_content_media_and_annotations_stay_context_scoped(): void
    {
        Storage::fake('local');

        $owner = Actor::factory()->create();
        $context = app(EnsurePersonalContext::class)->execute($owner->user);
        $definition = $this->activeNoteDefinition($context, $owner);
        $content = app(CreateContextContent::class)->execute(
            $context,
            $definition,
            $owner->user,
            'Media note',
            ['body' => 'Body'],
        );

        $upload = UploadedFile::fake()->image('context.png', 200, 200);
        $content = app(AttachAssetToSpaceContent::class)->execute(
            $content,
            $owner->user,
            $upload,
            'owned',
        );

        $draft = $content->draftRevisionRecord();
        $this->assertNotNull($draft);
        $asset = $draft->assets()->sole();
        $this->assertNull($asset->group_space_id);
        $this->assertSame($context->id, $asset->context_id);

        $content = app(PublishSpaceContent::class)->execute($content, $owner->user);
        $revision = $content->activeRevisionRecord();
        $this->assertNotNull($revision);

        $annotation = app(AddSpaceContentAnnotation::class)->execute(
            $content,
            $revision,
            $owner->user,
            'Context annotation',
        );
        $this->assertSame($content->id, $annotation->space_content_id);

        $this->actingAs($owner->user)
            ->get(route('contexts.contents.assets.show', [$context, $content, $asset]))
            ->assertOk();

        $wrongContext = app(EnsurePersonalContext::class)->execute(Actor::factory()->create()->user);
        $this->actingAs($owner->user)
            ->get(route('contexts.contents.assets.show', [$wrongContext, $content, $asset]))
            ->assertNotFound();
    }

    private function activeNoteDefinition(Context $context, Actor $manager): SpaceContentDefinition
    {
        $definition = app(CreateContextContentDefinition::class)->execute(
            $context,
            $manager->user,
            'Note',
            null,
            [[
                'key' => 'body',
                'label' => 'Body',
                'type' => 'long_text',
                'required' => true,
                'help' => null,
                'options' => [],
            ]],
        );

        return app(ActivateSpaceContentDefinition::class)->execute($definition, $manager->user);
    }
}
