<?php

namespace Tests\Feature;

use App\Actions\Content\CreateContentFromBlueprint;
use App\Actions\Content\EnsureSystemContentBlueprints;
use App\Actions\Contexts\EnsureAdmissionContext;
use App\Actions\Contexts\EnsurePersonalContext;
use App\Actions\Groups\CreateGroup;
use App\Livewire\Contexts\ContentIndex;
use App\Models\Actor;
use App\Models\Admission;
use App\Models\ContentBlueprint;
use App\Models\ContentBlueprintVersion;
use App\Models\SpaceContentRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class ContentBlueprintCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_personal_content_is_created_from_exact_blueprint_version_without_a_group(): void
    {
        $actor = Actor::factory()->create();
        $context = app(EnsurePersonalContext::class)->execute($actor->user);
        $blueprint = $this->systemBlueprint('evidence-work-sample');
        $version = $blueprint->activeVersionRecord();

        $this->assertInstanceOf(ContentBlueprintVersion::class, $version);

        $content = app(CreateContentFromBlueprint::class)->execute(
            $context,
            $version,
            $actor->user,
            'Laravel API project',
            [
                'work_date' => '2026-09-22',
                'role' => 'Backend developer',
                'summary' => 'Implemented a production API.',
                'outcome' => 'Delivered and reviewed.',
            ],
        );

        $this->assertNull($content->group_space_id);
        $this->assertSame($context->id, $content->context_id);
        $this->assertSame($version->id, $content->content_blueprint_version_id);
        $this->assertSame($version->id, $content->definition->content_blueprint_version_id);

        $revision = $content->draftRevisionRecord();
        $this->assertInstanceOf(SpaceContentRevision::class, $revision);
        $this->assertSame(SpaceContentRevision::COMPOSITION_BLOCKS, $revision->composition_mode);
        $this->assertSame('showcase', $revision->render_template_key);
        $this->assertCount(4, $revision->blocks()->get());

        $second = app(CreateContentFromBlueprint::class)->execute(
            $context,
            $version,
            $actor->user,
            'Second Laravel project',
            [
                'work_date' => null,
                'role' => null,
                'summary' => 'Another work sample.',
                'outcome' => null,
            ],
        );

        $this->assertSame($content->space_content_definition_id, $second->space_content_definition_id);
        $this->assertDatabaseCount('space_content_definitions', 1);
        $this->assertDatabaseMissing('groups', []);
    }

    public function test_admission_candidate_can_use_system_blueprint_without_definition_management_or_membership(): void
    {
        $reviewer = Actor::factory()->create();
        $candidate = Actor::factory()->create();
        $group = app(CreateGroup::class)->execute($reviewer, 'Blueprint admission group', null);
        $space = $group->spaces()->sole();
        $admission = Admission::factory()->create([
            'group_id' => $group->id,
            'candidate_actor_id' => $candidate->id,
            'status' => 'under_review',
        ]);
        $context = app(EnsureAdmissionContext::class)->execute($admission, $candidate->user);
        $version = $this->systemBlueprint('evidence-work-sample')->activeVersionRecord();

        $this->assertInstanceOf(ContentBlueprintVersion::class, $version);
        $this->assertFalse(Gate::forUser($candidate->user)->allows('manageDefinitions', $context));
        $this->assertTrue(Gate::forUser($candidate->user)->allows('createContent', $context));

        $content = app(CreateContentFromBlueprint::class)->execute(
            $context,
            $version,
            $candidate->user,
            'Candidate work sample',
            [
                'work_date' => null,
                'role' => 'Contributor',
                'summary' => 'Evidence submitted before membership.',
                'outcome' => null,
            ],
        );

        $this->assertSame($context->id, $content->context_id);
        $this->assertDatabaseMissing('group_memberships', [
            'group_id' => $group->id,
            'actor_id' => $candidate->id,
        ]);
        $this->assertFalse(Gate::forUser($candidate->user)->allows('view', $space));
    }

    public function test_existing_content_stays_pinned_when_blueprint_gets_a_new_active_version(): void
    {
        $actor = Actor::factory()->create();
        $context = app(EnsurePersonalContext::class)->execute($actor->user);
        $blueprint = ContentBlueprint::factory()->create([
            'owner_actor_id' => $actor->id,
        ]);
        $v1 = ContentBlueprintVersion::factory()->published()->create([
            'content_blueprint_id' => $blueprint->id,
        ]);

        $content = app(CreateContentFromBlueprint::class)->execute(
            $context,
            $v1,
            $actor->user,
            'Pinned work',
            ['body' => 'Version one'],
        );

        $v2 = ContentBlueprintVersion::factory()->published()->create([
            'content_blueprint_id' => $blueprint->id,
            'version' => 2,
            'definition_schema' => [
                'fields' => [[
                    'key' => 'body',
                    'label' => 'Body',
                    'type' => 'long_text',
                    'required' => true,
                    'help' => 'Version two',
                    'options' => [],
                ]],
            ],
        ]);

        $content->refresh();

        $this->assertSame($v1->id, $content->content_blueprint_version_id);
        $this->assertSame($v2->id, $blueprint->refresh()->active_version_id);
    }

    public function test_context_library_uses_blueprints_and_created_content_opens_advanced_studio(): void
    {
        $actor = Actor::factory()->create();
        $context = app(EnsurePersonalContext::class)->execute($actor->user);
        $blueprint = $this->systemBlueprint('note-diary');
        $version = $blueprint->activeVersionRecord();

        $this->assertInstanceOf(ContentBlueprintVersion::class, $version);

        Livewire::actingAs($actor->user)
            ->test(ContentIndex::class, ['context' => $context])
            ->call('openCreator')
            ->assertSee('Note / Diary')
            ->call('selectBlueprint', $version->id)
            ->set('title', 'Today')
            ->set('payload.body', 'A useful reflection.')
            ->call('createFromBlueprint')
            ->assertRedirect();

        $content = $context->contents()->sole();

        $this->actingAs($actor->user)
            ->get(route('contexts.contents.studio', [$context, $content]))
            ->assertOk()
            ->assertSee('Today')
            ->assertSee('A useful reflection.');
    }

    public function test_quick_blueprint_creation_hides_optional_fields_until_requested(): void
    {
        $actor = Actor::factory()->create();
        $context = app(EnsurePersonalContext::class)->execute($actor->user);
        $blueprint = $this->systemBlueprint('evidence-work-sample');
        $version = $blueprint->activeVersionRecord();

        $this->assertInstanceOf(ContentBlueprintVersion::class, $version);

        Livewire::actingAs($actor->user)
            ->test(ContentIndex::class, ['context' => $context])
            ->call('openCreator')
            ->call('selectBlueprint', $version->id)
            ->assertSee('What you did')
            ->assertDontSee('Work date')
            ->assertDontSee('Your role')
            ->assertSee('More details')
            ->call('toggleOptionalFields')
            ->assertSee('Work date')
            ->assertSee('Your role')
            ->assertSee('Result / outcome');
    }

    private function systemBlueprint(string $slug): ContentBlueprint
    {
        app(EnsureSystemContentBlueprints::class)->execute();

        return ContentBlueprint::query()->where('slug', $slug)->firstOrFail();
    }
}
