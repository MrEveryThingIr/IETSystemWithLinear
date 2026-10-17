<?php

namespace Tests\Feature;

use App\Actions\Content\CreateContentFromBlueprint;
use App\Actions\Content\EnsureSystemContentBlueprints;
use App\Actions\Contexts\EnsurePersonalContext;
use App\Actions\Groups\PublishSpaceContent;
use App\Actions\Groups\ReviseSpaceContent;
use App\Livewire\Contexts\ContentShow;
use App\Livewire\Contexts\ContentStudio;
use App\Models\Actor;
use App\Models\ContentBlueprint;
use App\Models\Context;
use App\Models\SpaceContent;
use App\Models\SpaceContentRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ContentStudioInformationArchitectureTest extends TestCase
{
    use RefreshDatabase;

    public function test_studio_is_one_workspace_with_tools_as_panels(): void
    {
        [$actor, $context, $content] = $this->lesson();

        Livewire::actingAs($actor->user)
            ->test(ContentStudio::class, ['context' => $context, 'content' => $content])
            ->assertOk()
            ->assertSeeHtml('data-studio-topbar')
            ->assertSeeHtml('data-studio-canvas')
            ->assertSeeHtml('data-studio-insert')
            ->assertSeeHtml('data-studio-inspector')
            ->assertSeeHtml('data-drawer="ai"')
            ->assertSeeHtml('data-drawer="appearance"')
            ->assertSeeHtml('data-drawer="history"')
            // Context is described in words, never as an id.
            ->assertSee(__('library.context.personal'))
            ->assertDontSee('Context #')
            // The old step-by-step workflow explainer is gone from the Studio.
            ->assertDontSee(__('workflow.content_shell.purpose'));
    }

    public function test_unchanged_save_creates_no_revision_and_a_title_change_creates_one(): void
    {
        [$actor, $context, $content] = $this->lesson();
        $before = $content->revisions()->count();

        $component = Livewire::actingAs($actor->user)
            ->test(ContentStudio::class, ['context' => $context, 'content' => $content])
            ->call('saveRevision');
        $this->assertSame($before, $content->revisions()->count());

        $component->set('title', 'Lesson 1 — renamed')
            ->assertSeeHtml('data-studio-unsaved')
            ->call('saveRevision')
            ->assertHasNoErrors()
            ->assertDontSeeHtml('data-studio-unsaved');

        $this->assertSame($before + 1, $content->revisions()->count());
        $this->assertSame('Lesson 1 — renamed', $content->fresh()->draftRevisionRecord()?->title);
    }

    public function test_writing_and_inserting_blocks_saves_through_the_existing_kernel(): void
    {
        [$actor, $context, $content] = $this->lesson();

        Livewire::actingAs($actor->user)
            ->test(ContentStudio::class, ['context' => $context, 'content' => $content])
            ->set('payload.body', 'Updated lesson text')
            ->call('addBlock', 'heading')
            ->set('blocks.2.data.text', 'Warm-up')
            ->call('saveRevision')
            ->assertHasNoErrors();

        $draft = $content->fresh()->draftRevisionRecord();
        $this->assertInstanceOf(SpaceContentRevision::class, $draft);
        $this->assertSame('Updated lesson text', $draft->payload['body']);
        $this->assertSame(SpaceContentRevision::COMPOSITION_BLOCKS, $draft->composition_mode);
        $this->assertSame(['field', 'field', 'heading'], $draft->blocks()->pluck('type')->all());
        $this->assertSame('Warm-up', $draft->blocks()->where('type', 'heading')->first()?->data['text']);
    }

    public function test_required_field_errors_are_shown_and_nothing_is_written(): void
    {
        [$actor, $context, $content] = $this->lesson();
        $before = $content->revisions()->count();

        Livewire::actingAs($actor->user)
            ->test(ContentStudio::class, ['context' => $context, 'content' => $content])
            ->set('payload.body', '')
            ->call('saveRevision')
            ->assertHasErrors('payload.body');

        $this->assertSame($before, $content->revisions()->count());
    }

    public function test_publish_saves_pending_edits_first(): void
    {
        [$actor, $context, $content] = $this->lesson();

        Livewire::actingAs($actor->user)
            ->test(ContentStudio::class, ['context' => $context, 'content' => $content])
            ->set('title', 'Published title')
            ->call('openPublishPanel')
            ->assertSet('publishPanelOpen', true)
            ->assertSeeHtml('data-studio-publish-panel')
            ->call('publish')
            ->assertHasNoErrors()
            ->assertSet('publishPanelOpen', false);

        $content->refresh();
        $this->assertSame('published', $content->status);
        $this->assertSame('Published title', $content->activeRevisionRecord()?->title);
    }

    public function test_change_from_another_panel_does_not_silently_overwrite_unsaved_edits(): void
    {
        [$actor, $context, $content] = $this->lesson();

        $studio = Livewire::actingAs($actor->user)
            ->test(ContentStudio::class, ['context' => $context, 'content' => $content])
            ->set('title', 'My unsaved title');

        // Another panel (for example AI) writes a revision.
        app(ReviseSpaceContent::class)->execute($content->fresh(), $actor->user, 'Written elsewhere', ['body' => 'From AI', 'objective' => null]);

        $studio->dispatch('content-revised')
            ->assertSet('draftChangedElsewhere', true)
            ->assertSet('title', 'My unsaved title')
            ->call('discardAndReload')
            ->assertSet('title', 'Written elsewhere')
            ->assertSet('payload.body', 'From AI');
    }

    public function test_clean_canvas_follows_changes_from_other_panels(): void
    {
        [$actor, $context, $content] = $this->lesson();

        $studio = Livewire::actingAs($actor->user)
            ->test(ContentStudio::class, ['context' => $context, 'content' => $content]);

        app(ReviseSpaceContent::class)->execute($content->fresh(), $actor->user, 'Applied by AI', ['body' => 'New body', 'objective' => null]);

        $studio->dispatch('content-revised')
            ->assertSet('draftChangedElsewhere', false)
            ->assertSet('title', 'Applied by AI');
    }

    public function test_preview_opens_the_draft_for_editors_only(): void
    {
        [$actor, $context, $content] = $this->lesson();
        $content = app(PublishSpaceContent::class)->execute($content, $actor->user);
        app(ReviseSpaceContent::class)->execute($content, $actor->user, 'Unpublished change', ['body' => 'Draft body', 'objective' => null]);

        Livewire::actingAs($actor->user)
            ->test(ContentStudio::class, ['context' => $context, 'content' => $content->fresh()])
            ->call('preview')
            ->assertRedirect(route('contexts.contents.show', [$context, $content, 'preview' => 'draft']));

        Livewire::withQueryParams(['preview' => 'draft'])
            ->actingAs($actor->user)
            ->test(ContentShow::class, ['context' => $context, 'content' => $content->fresh()])
            ->assertSet('previewingDraft', true)
            ->assertSee('Unpublished change')
            ->assertSeeHtml('data-reader-draft-preview');

        // Without the preview flag readers see the published edition.
        Livewire::withQueryParams([])
            ->actingAs($actor->user)
            ->test(ContentShow::class, ['context' => $context, 'content' => $content->fresh()])
            ->assertSet('previewingDraft', false)
            ->assertDontSee('Unpublished change');
    }

    public function test_reader_shows_place_in_words_and_tucks_provenance_away(): void
    {
        [$actor, $context, $content] = $this->lesson();
        $content = app(PublishSpaceContent::class)->execute($content, $actor->user);

        $this->actingAs($actor->user)
            ->get(route('contexts.contents.show', [$context, $content]))
            ->assertOk()
            ->assertSeeHtml('data-reader-place')
            ->assertSee(__('library.context.personal'))
            ->assertSeeHtml('data-reader-provenance');
    }

    /** @return array{Actor, Context, SpaceContent} */
    private function lesson(): array
    {
        $actor = Actor::factory()->create();
        $context = app(EnsurePersonalContext::class)->execute($actor->user);
        app(EnsureSystemContentBlueprints::class)->execute();
        $version = ContentBlueprint::query()->where('slug', 'lesson')->firstOrFail()->activeVersionRecord();
        $this->assertNotNull($version);

        $content = app(CreateContentFromBlueprint::class)->execute(
            $context,
            $version,
            $actor->user,
            'Lesson 1',
            ['objective' => null, 'body' => 'Introductions'],
        );

        return [$actor, $context, $content];
    }
}
