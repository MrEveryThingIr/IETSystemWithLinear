<?php

namespace Tests\Feature;

use App\Actions\Content\CreateContentEvidenceReference;
use App\Actions\Content\CreateContentFromBlueprint;
use App\Actions\Content\EnsureSystemContentBlueprints;
use App\Actions\Contexts\EnsurePersonalContext;
use App\Actions\Groups\PublishSpaceContent;
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
            ->assertSee('Document Layout');

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

    public function test_evidence_reference_can_bind_revision_block_and_field_without_following_future_edits(): void
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

        $this->actingAs($actor->user)
            ->get(route('content-evidence.show', $whole))
            ->assertRedirect(route('contexts.contents.show', [
                $context,
                $content,
                'evidence' => $whole->uuid,
            ]));
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
