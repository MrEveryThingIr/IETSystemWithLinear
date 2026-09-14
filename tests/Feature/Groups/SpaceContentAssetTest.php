<?php

namespace Tests\Feature\Groups;

use App\Actions\Groups\ActivateSpaceContentDefinition;
use App\Actions\Groups\AttachAssetToSpaceContent;
use App\Actions\Groups\CreateGroup;
use App\Actions\Groups\CreateGroupSpace;
use App\Actions\Groups\CreateSpaceContent;
use App\Actions\Groups\CreateSpaceContentDefinition;
use App\Actions\Groups\PublishSpaceContent;
use App\Actions\Groups\RemoveAssetFromSpaceContent;
use App\Actions\Groups\ReviseSpaceContent;
use App\Actions\Groups\SetGroupSpaceParticipant;
use App\Livewire\Groups\SpaceContentShow;
use App\Models\Actor;
use App\Models\Asset;
use App\Models\Group;
use App\Models\GroupSpace;
use App\Models\SpaceContent;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SpaceContentAssetTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_author_upload_creates_private_revision_and_private_asset(): void
    {
        Storage::fake('local');
        [$group, $space, $owner, $author, $reader, $content] = $this->fixture();

        $content = app(AttachAssetToSpaceContent::class)->execute(
            $content,
            $author->user,
            UploadedFile::fake()->create('voice.webm', 24, 'audio/webm'),
            'owned',
            'Spoken explanation',
        );

        $asset = Asset::query()->sole();
        $draft = $content->draftRevisionRecord();

        $this->assertNotNull($draft);
        $this->assertSame(2, $draft->revision);
        $this->assertSame($space->id, $asset->group_space_id);
        $this->assertSame($author->id, $asset->uploaded_by_actor_id);
        $this->assertSame('audio/webm', $asset->mime_type);
        $this->assertSame('owned', $asset->rights_status);
        Storage::disk('local')->assertExists($asset->storage_key);
        $this->assertDatabaseHas('space_content_revision_assets', [
            'space_content_revision_id' => $draft->id,
            'asset_id' => $asset->id,
            'caption' => 'Spoken explanation',
        ]);
    }

    public function test_reader_can_stream_published_asset_but_not_draft_only_asset(): void
    {
        Storage::fake('local');
        [$group, $space, $owner, $author, $reader, $content] = $this->fixture();

        $content = app(PublishSpaceContent::class)->execute($content, $author->user);
        $content = app(AttachAssetToSpaceContent::class)->execute(
            $content,
            $author->user,
            UploadedFile::fake()->create('private-next.webm', 16, 'audio/webm'),
            'owned',
        );
        $draftAsset = Asset::query()->sole();

        $draftUrl = route('groups.spaces.contents.assets.show', [$group, $space, $content, $draftAsset]);
        $this->actingAs($reader->user)->get($draftUrl)->assertNotFound();
        $this->actingAs($author->user)->get($draftUrl)->assertOk();

        $content = app(PublishSpaceContent::class)->execute($content, $author->user);
        $this->actingAs($reader->user)
            ->get($draftUrl)
            ->assertOk()
            ->assertHeader('Content-Type', 'audio/webm')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_private_study_media_blocks_space_publication(): void
    {
        Storage::fake('local');
        [$group, $space, $owner, $author, $reader, $content] = $this->fixture();

        $content = app(AttachAssetToSpaceContent::class)->execute(
            $content,
            $author->user,
            UploadedFile::fake()->create('study-only.pdf', 12, 'application/pdf'),
            'private_study_only',
        );

        try {
            app(PublishSpaceContent::class)->execute($content, $author->user);
            $this->fail('Private-study-only media was published.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $this->assertNull($content->fresh()->active_revision_id);
    }

    public function test_text_revision_carries_media_forward_without_mutating_old_revision(): void
    {
        Storage::fake('local');
        [$group, $space, $owner, $author, $reader, $content] = $this->fixture();

        $content = app(AttachAssetToSpaceContent::class)->execute(
            $content,
            $author->user,
            UploadedFile::fake()->create('diagram.png', 8, 'image/png'),
            'owned',
        );
        $asset = Asset::query()->sole();
        $mediaRevision = $content->draftRevisionRecord();
        $this->assertNotNull($mediaRevision);

        $content = app(ReviseSpaceContent::class)->execute(
            $content,
            $author->user,
            'Revised with media',
            ['body' => 'Changed text'],
        );
        $next = $content->draftRevisionRecord();
        $this->assertNotNull($next);

        $this->assertDatabaseHas('space_content_revision_assets', [
            'space_content_revision_id' => $mediaRevision->id,
            'asset_id' => $asset->id,
        ]);
        $this->assertDatabaseHas('space_content_revision_assets', [
            'space_content_revision_id' => $next->id,
            'asset_id' => $asset->id,
        ]);
    }

    public function test_removing_media_creates_new_revision_and_preserves_old_revision_evidence(): void
    {
        Storage::fake('local');
        [$group, $space, $owner, $author, $reader, $content] = $this->fixture();

        $content = app(AttachAssetToSpaceContent::class)->execute(
            $content,
            $author->user,
            UploadedFile::fake()->create('photo.png', 8, 'image/png'),
            'owned',
        );
        $asset = Asset::query()->sole();
        $withMedia = $content->draftRevisionRecord();
        $this->assertNotNull($withMedia);

        $content = app(RemoveAssetFromSpaceContent::class)->execute($content, $asset, $author->user);
        $withoutMedia = $content->draftRevisionRecord();
        $this->assertNotNull($withoutMedia);

        $this->assertDatabaseHas('space_content_revision_assets', [
            'space_content_revision_id' => $withMedia->id,
            'asset_id' => $asset->id,
        ]);
        $this->assertDatabaseMissing('space_content_revision_assets', [
            'space_content_revision_id' => $withoutMedia->id,
            'asset_id' => $asset->id,
        ]);
        Storage::disk('local')->assertExists($asset->storage_key);
    }

    public function test_unsupported_media_type_is_rejected_before_asset_creation(): void
    {
        Storage::fake('local');
        [$group, $space, $owner, $author, $reader, $content] = $this->fixture();

        try {
            app(AttachAssetToSpaceContent::class)->execute(
                $content,
                $author->user,
                UploadedFile::fake()->create('page.html', 4, 'text/html'),
                'owned',
            );
            $this->fail('Unsupported HTML upload was accepted.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $this->assertDatabaseCount('assets', 0);
    }

    public function test_livewire_upload_path_adds_media_and_renders_it_in_private_draft(): void
    {
        Storage::fake('local');
        [$group, $space, $owner, $author, $reader, $content] = $this->fixture();

        Livewire::actingAs($author->user)
            ->test(SpaceContentShow::class, compact('group', 'space', 'content'))
            ->set('assetRightsStatus', 'owned')
            ->set('assetCaption', 'Voice answer draft')
            ->set('assetUpload', UploadedFile::fake()->create('voice.webm', 12, 'audio/webm'))
            ->call('attachAsset')
            ->assertHasNoErrors()
            ->assertSee('voice.webm')
            ->assertSee('Voice answer draft');

        $this->assertDatabaseCount('assets', 1);
        $this->assertNotNull($content->fresh()->draft_revision_id);
    }

    /** @return array{Group, GroupSpace, Actor, Actor, Actor, SpaceContent} */
    private function fixture(): array
    {
        $owner = Actor::factory()->create();
        $author = Actor::factory()->create();
        $reader = Actor::factory()->create();
        $group = app(CreateGroup::class)->execute($owner, 'Media group', null);
        $space = app(CreateGroupSpace::class)->execute($group, $owner->user, 'Media', 'restricted');

        app(SetGroupSpaceParticipant::class)->execute($space, $owner, $owner->user, 'allow', 'manager');
        app(SetGroupSpaceParticipant::class)->execute($space, $author, $owner->user, 'allow', 'participant');
        app(SetGroupSpaceParticipant::class)->execute($space, $reader, $owner->user, 'allow', 'participant');

        $definition = app(CreateSpaceContentDefinition::class)->execute(
            $space,
            $owner->user,
            'Article',
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
        $definition = app(ActivateSpaceContentDefinition::class)->execute($definition, $owner->user);

        $content = app(CreateSpaceContent::class)->execute(
            $space,
            $definition,
            $author->user,
            'Media article',
            ['body' => 'Initial text'],
        );

        return [$group, $space, $owner, $author, $reader, $content];
    }
}
