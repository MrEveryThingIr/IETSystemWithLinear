<?php

namespace Tests\Feature;

use App\Actions\Content\CreateContentEvidenceReference;
use App\Actions\Content\CreateContentFromBlueprint;
use App\Actions\Content\EnsureSystemContentBlueprints;
use App\Actions\Contexts\EnsureGroupSpaceContext;
use App\Actions\Contexts\EnsurePersonalContext;
use App\Actions\Groups\CreateGroup;
use App\Actions\Groups\PublishSpaceContent;
use App\Actions\Groups\ReviseSpaceContent;
use App\Actions\Groups\SaveSpaceContentRenderTemplate;
use App\Actions\Groups\UpdateSpaceContentPresentation;
use App\ContentEvidenceTarget;
use App\Livewire\Contexts\ContentAppearance;
use App\Livewire\Contexts\ContentBlocks;
use App\Livewire\Contexts\ContentOutline;
use App\Models\Actor;
use App\Models\ContentBlueprint;
use App\Models\Context;
use App\Models\SpaceContent;
use App\Models\SpaceContentRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ContentUnifiedExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_personal_content_has_blocks_appearance_outline_and_mature_reader(): void
    {
        $actor = Actor::factory()->create();
        $context = app(EnsurePersonalContext::class)->execute($actor->user);
        $content = $this->content($actor, $context);

        Livewire::actingAs($actor->user)
            ->test(ContentBlocks::class, ['context' => $context, 'content' => $content])
            ->assertOk()
            ->assertSet('compositionMode', SpaceContentRevision::COMPOSITION_BLOCKS);

        Livewire::actingAs($actor->user)
            ->test(ContentAppearance::class, ['context' => $context, 'content' => $content])
            ->assertOk();

        Livewire::actingAs($actor->user)
            ->test(ContentOutline::class, ['context' => $context, 'content' => $content])
            ->assertOk();

        $content = app(PublishSpaceContent::class)->execute($content, $actor->user);

        $this->actingAs($actor->user)
            ->get(route('contexts.contents.show', [$context, $content]))
            ->assertOk()
            ->assertSee('Unified work sample')
            ->assertSee('Evidence submitted through one Content kernel.');
    }

    public function test_evidence_reference_stays_bound_to_exact_sealed_revision_after_later_publication(): void
    {
        $actor = Actor::factory()->create();
        $context = app(EnsurePersonalContext::class)->execute($actor->user);
        $content = $this->content($actor, $context);
        $content = app(PublishSpaceContent::class)->execute($content, $actor->user);
        $revision = $content->activeRevisionRecord();

        $this->assertInstanceOf(SpaceContentRevision::class, $revision);
        $block = $revision->blocks()->firstOrFail();

        $whole = app(CreateContentEvidenceReference::class)->execute(
            $content,
            $revision,
            $actor->user,
        );
        $blockReference = app(CreateContentEvidenceReference::class)->execute(
            $content,
            $revision,
            $actor->user,
            ContentEvidenceTarget::Block,
            $block->uuid,
        );
        $fieldReference = app(CreateContentEvidenceReference::class)->execute(
            $content,
            $revision,
            $actor->user,
            ContentEvidenceTarget::Field,
            null,
            'summary',
        );

        $this->assertSame($revision->id, $whole->space_content_revision_id);
        $this->assertSame($block->uuid, $blockReference->target_uuid);
        $this->assertSame('summary', $fieldReference->field_key);

        $content = app(ReviseSpaceContent::class)->execute(
            $content,
            $actor->user,
            'Newer work sample',
            [
                'work_date' => null,
                'role' => 'Developer',
                'summary' => 'This is a later edition.',
                'outcome' => 'Updated.',
            ],
        );
        $content = app(PublishSpaceContent::class)->execute($content, $actor->user);
        $this->assertNotSame($revision->id, $content->active_revision_id);

        $this->actingAs($actor->user)
            ->followingRedirects()
            ->get(route('content-evidence.show', $whole))
            ->assertOk()
            ->assertSee('Unified work sample')
            ->assertSee('Evidence submitted through one Content kernel.')
            ->assertDontSee('This is a later edition.');
    }

    public function test_unsealed_draft_cannot_become_historical_evidence(): void
    {
        $actor = Actor::factory()->create();
        $context = app(EnsurePersonalContext::class)->execute($actor->user);
        $content = $this->content($actor, $context);
        $revision = $content->draftRevisionRecord();

        $this->assertInstanceOf(SpaceContentRevision::class, $revision);

        try {
            app(CreateContentEvidenceReference::class)->execute($content, $revision, $actor->user);
            $this->fail('Draft Content must not become a historical evidence reference.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
    }

    public function test_personal_saved_presentation_templates_are_isolated_by_context(): void
    {
        $owner = Actor::factory()->create();
        $context = app(EnsurePersonalContext::class)->execute($owner->user);
        $content = $this->content($owner, $context);

        $template = app(SaveSpaceContentRenderTemplate::class)->execute(
            $content,
            $owner->user,
            'My evidence card',
            'showcase',
            ['accent' => '#123456'],
        );

        $styled = app(UpdateSpaceContentPresentation::class)->execute(
            $content,
            $owner->user,
            'custom:'.$template->uuid,
            [],
        );
        $this->assertSame($template->uuid, $styled->draftRevisionRecord()?->render_template_uuid);

        $other = Actor::factory()->create();
        $otherContext = app(EnsurePersonalContext::class)->execute($other->user);
        $otherContent = $this->content($other, $otherContext);

        try {
            app(UpdateSpaceContentPresentation::class)->execute(
                $otherContent,
                $other->user,
                'custom:'.$template->uuid,
                [],
            );
            $this->fail('A saved presentation template must not cross Context boundaries.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
    }

    public function test_legacy_group_content_studio_redirects_to_canonical_context_surface(): void
    {
        $owner = Actor::factory()->create();
        $group = app(CreateGroup::class)->execute($owner, 'Unified content group', null);
        $space = $group->spaces()->sole();
        $context = app(EnsureGroupSpaceContext::class)->execute($space);
        $content = $this->content($owner, $context);

        $this->actingAs($owner->user)
            ->get(route('groups.spaces.contents.studio', [$group, $space, $content]))
            ->assertRedirect(route('contexts.contents.studio', [$context, $content]));
    }

    private function content(Actor $actor, Context $context): SpaceContent
    {
        app(EnsureSystemContentBlueprints::class)->execute();
        $blueprint = ContentBlueprint::query()->where('slug', 'evidence-work-sample')->firstOrFail();
        $version = $blueprint->activeVersionRecord();
        $this->assertNotNull($version);

        return app(CreateContentFromBlueprint::class)->execute(
            $context,
            $version,
            $actor->user,
            'Unified work sample',
            [
                'work_date' => null,
                'role' => 'Developer',
                'summary' => 'Evidence submitted through one Content kernel.',
                'outcome' => 'Accepted.',
            ],
        );
    }
}
