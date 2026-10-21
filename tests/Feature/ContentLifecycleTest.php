<?php

namespace Tests\Feature;

use App\Actions\Content\CreateContentFromBlueprint;
use App\Actions\Content\CreateContentInvitation;
use App\Actions\Content\DeleteContentPermanently;
use App\Actions\Content\EnsureSystemContentBlueprints;
use App\Actions\Content\RenameContent;
use App\Actions\Content\SetContentPublicAccess;
use App\Actions\Contexts\EnsurePersonalContext;
use App\Actions\Groups\AddSpaceContentAnnotation;
use App\Actions\Groups\AttachAssetToSpaceContent;
use App\Actions\Groups\PublishSpaceContent;
use App\Actions\Groups\ReviseSpaceContent;
use App\Livewire\Content\Workspace;
use App\Livewire\Contexts\ContentStudio;
use App\Models\Actor;
use App\Models\Asset;
use App\Models\ContentBlueprint;
use App\Models\Context;
use App\Models\SpaceContent;
use App\Models\SpaceContentAnnotation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Concerns\PublishesFeatureSurfaces;
use Tests\TestCase;

class ContentLifecycleTest extends TestCase
{
    use PublishesFeatureSurfaces;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_author_deletes_unengaged_content_with_everything_that_belongs_only_to_it(): void
    {
        [$author, $context, $content] = $this->article('Test garbage');
        $content = app(AttachAssetToSpaceContent::class)->execute($content, $author->user, UploadedFile::fake()->image('map.png', 20, 20), 'owned');
        $content = app(PublishSpaceContent::class)->execute($content, $author->user);
        app(SetContentPublicAccess::class)->execute($content, $author->user, SpaceContent::ACCESS_PUBLIC);
        app(CreateContentInvitation::class)->execute($content, $author->user, 'Sara');
        app(AddSpaceContentAnnotation::class)->execute($content, $content->activeRevisionRecord(), $author->user, 'my own note', kind: SpaceContentAnnotation::KIND_NOTE, visibility: SpaceContentAnnotation::VISIBILITY_PRIVATE);
        $content = app(ReviseSpaceContent::class)->execute($content->fresh(), $author->user, 'Test garbage', ['summary' => 'changed', 'body' => 'b']);

        $asset = Asset::query()->sole();
        Storage::disk('local')->assertExists($asset->storage_key);

        $assessment = app(DeleteContentPermanently::class)->assess($content, $author->user);
        $this->assertSame([], $assessment['blockers']);
        $this->assertSame(['versions' => 3, 'own_notes' => 1, 'files' => 1, 'invitation_links' => 1, 'public' => 1], $assessment['removes']);

        try {
            app(DeleteContentPermanently::class)->execute($content, $author->user, 'wrong');
            $this->fail('A wrong confirmation must not delete.');
        } catch (ValidationException) {
            $this->assertTrue(SpaceContent::query()->whereKey($content->id)->exists());
        }

        app(DeleteContentPermanently::class)->execute($content, $author->user, ' Test garbage ');

        $this->assertFalse(SpaceContent::query()->whereKey($content->id)->exists());
        foreach (['space_content_revisions', 'space_content_annotations', 'content_invitations', 'space_content_revision_assets', 'space_content_lifecycle_events', 'assets'] as $table) {
            $this->assertSame(0, DB::table($table)->count(), $table);
        }
        Storage::disk('local')->assertMissing($asset->storage_key);
        $this->get(route('public-content.show', $content->uuid))->assertNotFound();
    }

    public function test_content_others_engaged_with_can_only_be_archived(): void
    {
        [$author, $context, $content] = $this->article('Shared lesson');
        $content = app(PublishSpaceContent::class)->execute($content, $author->user);
        $reader = Actor::factory()->create();
        DB::table('space_content_annotations')->insert([
            'uuid' => (string) Str::uuid(),
            'space_content_id' => $content->id,
            'space_content_revision_id' => $content->active_revision_id,
            'author_actor_id' => $reader->id,
            'kind' => 'question',
            'visibility' => 'space',
            'status' => 'active',
            'body' => 'What does this mean?',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->publishSurfaces($author->user, ['content']);
        Livewire::actingAs($author->user)
            ->test(Workspace::class)
            ->call('openDelete', $content->uuid)
            ->assertSet('deleteAssessment.blockers', ['others_notes' => 1])
            ->assertSeeHtml('data-delete-blocked')
            ->assertDontSeeHtml('data-delete-confirm')
            ->call('archiveInstead');

        $this->assertSame('archived', $content->fresh()->status);

        $this->expectException(ValidationException::class);
        app(DeleteContentPermanently::class)->execute($content->fresh(), $author->user, 'Shared lesson');
    }

    public function test_only_the_author_can_delete(): void
    {
        [, , $content] = $this->article('Mine');
        $stranger = Actor::factory()->create();

        $this->expectException(AuthorizationException::class);
        app(DeleteContentPermanently::class)->execute($content, $stranger->user, 'Mine');
    }

    public function test_workspace_menu_deletes_and_renames(): void
    {
        [$author, , $draft] = $this->article('Old draft name');
        [, , $other] = $this->article('Second', $author);
        $published = app(PublishSpaceContent::class)->execute($other, $author->user);
        $this->publishSurfaces($author->user, ['content']);

        Livewire::actingAs($author->user)
            ->test(Workspace::class)
            ->assertSeeHtml('data-content-card-menu')
            ->call('openRename', $published->uuid)
            ->assertSet('renameTitle', 'Second')
            ->set('renameTitle', 'Second, renamed')
            ->call('rename')
            ->assertHasNoErrors()
            ->assertSee('Second, renamed')
            ->call('openDelete', $draft->uuid)
            ->assertSeeHtml('data-delete-confirm')
            ->set('deleteConfirmation', 'Old draft name')
            ->call('deleteForGood')
            ->assertHasNoErrors()
            ->assertSee(__('studio.delete.done', ['title' => 'Old draft name']));

        $published->refresh();
        $this->assertSame('Second, renamed', $published->activeRevisionRecord()->title);
        $this->assertNull($published->draft_revision_id);
        $this->assertFalse(SpaceContent::query()->whereKey($draft->id)->exists());
    }

    public function test_renaming_with_unpublished_changes_renames_the_draft_only(): void
    {
        [$author, $context, $content] = $this->article('Live title');
        $content = app(PublishSpaceContent::class)->execute($content, $author->user);
        $content = app(ReviseSpaceContent::class)->execute($content, $author->user, 'Live title', ['summary' => 'Work in progress', 'body' => 'b']);

        $result = app(RenameContent::class)->execute($content, $author->user, 'Next title');

        $this->assertFalse($result['live']);
        $this->assertSame('Live title', $result['content']->activeRevisionRecord()->title);
        $this->assertSame('Next title', $result['content']->draftRevisionRecord()->title);
        $this->assertSame('Work in progress', $result['content']->draftRevisionRecord()->payload['summary']);
    }

    public function test_studio_deletes_and_returns_to_the_content_list(): void
    {
        [$author, $context, $content] = $this->article('Studio garbage');
        $this->publishSurfaces($author->user, ['content']);

        Livewire::actingAs($author->user)
            ->test(ContentStudio::class, ['context' => $context, 'content' => $content])
            ->assertSeeHtml('data-studio-delete')
            ->assertSeeHtml('data-studio-rename')
            ->call('openDelete', $content->uuid)
            ->set('deleteConfirmation', 'Studio garbage')
            ->call('deleteForGood')
            ->assertRedirect(route('experience.content'));

        $this->assertSame(0, SpaceContent::query()->count());
    }

    /** @return array{Actor, Context, SpaceContent} */
    private function article(string $title, ?Actor $author = null): array
    {
        $author ??= Actor::factory()->create();
        $context = app(EnsurePersonalContext::class)->execute($author->user);
        app(EnsureSystemContentBlueprints::class)->execute();
        $version = ContentBlueprint::query()->where('slug', 'article')->firstOrFail()->activeVersionRecord();
        $content = app(CreateContentFromBlueprint::class)->execute($context, $version, $author->user, $title, [
            'summary' => 'The garden was quiet at dawn.',
            'body' => 'A longer account of the morning.',
        ]);

        return [$author, $context, $content];
    }
}
