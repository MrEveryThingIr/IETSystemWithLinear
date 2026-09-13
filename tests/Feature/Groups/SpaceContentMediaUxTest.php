<?php

namespace Tests\Feature\Groups;

use App\Actions\Groups\ActivateSpaceContentDefinition;
use App\Actions\Groups\AttachAssetToSpaceContent;
use App\Actions\Groups\CreateGroup;
use App\Actions\Groups\CreateGroupSpace;
use App\Actions\Groups\CreateSpaceContent;
use App\Actions\Groups\CreateSpaceContentDefinition;
use App\Actions\Groups\PublishSpaceContent;
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
use Tests\TestCase;

class SpaceContentMediaUxTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_publish_blocker_stays_inline_and_rights_can_be_resolved_without_reuploading(): void
    {
        Storage::fake('local');
        [$group, $space, $owner, $author, $content] = $this->fixture();

        $content = app(AttachAssetToSpaceContent::class)->execute(
            $content,
            $author->user,
            UploadedFile::fake()->create('reference.pdf', 12, 'application/pdf'),
            'private_study_only',
        );
        $asset = Asset::query()->sole();

        $component = Livewire::actingAs($author->user)
            ->test(SpaceContentShow::class, compact('group', 'space', 'content'))
            ->assertSee('Publishing is waiting on media rights')
            ->call('publish')
            ->assertHasErrors(['publish']);

        $this->assertNull($content->fresh()->active_revision_id);

        $component
            ->call('updateAssetRights', $asset->id, 'owned')
            ->assertHasNoErrors()
            ->assertDontSee('Publishing is waiting on media rights')
            ->call('publish')
            ->assertHasNoErrors();

        $this->assertSame('owned', $asset->fresh()->rights_status);
        $this->assertNotNull($content->fresh()->active_revision_id);
    }

    public function test_recorded_upload_path_marks_new_audio_as_owned(): void
    {
        Storage::fake('local');
        [$group, $space, $owner, $author, $content] = $this->fixture();

        Livewire::actingAs($author->user)
            ->test(SpaceContentShow::class, compact('group', 'space', 'content'))
            ->set('recordingCaption', 'Recorded in browser')
            ->set('assetUpload', UploadedFile::fake()->create('recording.webm', 10, 'audio/webm'))
            ->call('attachRecordedAsset')
            ->assertHasNoErrors()
            ->assertSee('recording.webm');

        $asset = Asset::query()->sole();
        $this->assertSame('owned', $asset->rights_status);
        $this->assertDatabaseHas('space_content_revision_assets', [
            'asset_id' => $asset->id,
            'caption' => 'Recorded in browser',
        ]);
    }

    public function test_recorder_source_is_first_render_ready_without_late_alpine_registration(): void
    {
        $source = file_get_contents(resource_path('views/livewire/groups/space-content-show.blade.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString('id="content-audio-recorder-{{ $content->id }}"', $source);
        $this->assertStringContainsString('wire:ignore', $source);
        $this->assertStringContainsString('const suffix = @js((string) $content->id);', $source);
        $this->assertStringContainsString("\$wire.upload('assetUpload'", $source);
        $this->assertStringContainsString("\$wire.call('attachRecordedAsset')", $source);
        $this->assertStringNotContainsString("Alpine.data('contentAudioRecorder'", $source);
        $this->assertStringNotContainsString('x-data="contentAudioRecorder"', $source);
    }

    public function test_published_asset_cannot_be_downgraded_to_private_only_rights(): void
    {
        Storage::fake('local');
        [$group, $space, $owner, $author, $content] = $this->fixture();

        $content = app(AttachAssetToSpaceContent::class)->execute(
            $content,
            $author->user,
            UploadedFile::fake()->create('published.png', 8, 'image/png'),
            'owned',
        );
        $content = app(PublishSpaceContent::class)->execute($content, $author->user);
        $asset = Asset::query()->sole();

        app(AttachAssetToSpaceContent::class)->execute(
            $content,
            $author->user,
            UploadedFile::fake()->create('next.txt', 4, 'text/plain'),
            'owned',
        );

        Livewire::actingAs($author->user)
            ->test(SpaceContentShow::class, compact('group', 'space', 'content'))
            ->call('updateAssetRights', $asset->id, 'private_study_only')
            ->assertHasErrors(['assetRights.'.$asset->id]);

        $this->assertSame('owned', $asset->fresh()->rights_status);
    }

    /** @return array{Group, GroupSpace, Actor, Actor, SpaceContent} */
    private function fixture(): array
    {
        $owner = Actor::factory()->create();
        $author = Actor::factory()->create();
        $group = app(CreateGroup::class)->execute($owner, 'Media UX group', null);
        $space = app(CreateGroupSpace::class)->execute($group, $owner->user, 'Media UX', 'restricted');

        app(SetGroupSpaceParticipant::class)->execute($space, $owner, $owner->user, 'allow', 'manager');
        app(SetGroupSpaceParticipant::class)->execute($space, $author, $owner->user, 'allow', 'participant');

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
            'Media UX article',
            ['body' => 'Initial text'],
        );

        return [$group, $space, $owner, $author, $content];
    }
}
