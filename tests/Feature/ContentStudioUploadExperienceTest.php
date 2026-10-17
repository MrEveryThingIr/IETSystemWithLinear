<?php

namespace Tests\Feature;

use App\Actions\Content\CreateContentFromBlueprint;
use App\Actions\Content\EnsureSystemContentBlueprints;
use App\Actions\Contexts\EnsurePersonalContext;
use App\Livewire\Contexts\ContentStudio;
use App\Models\Actor;
use App\Models\ContentBlueprint;
use App\Models\Context;
use App\Models\SpaceContent;
use App\Support\MediaLimits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ContentStudioUploadExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_inserted_image_lands_in_the_document_bound_to_its_own_placement(): void
    {
        Storage::fake('local');
        [$actor, $context, $content] = $this->lesson();

        Livewire::actingAs($actor->user)
            ->test(ContentStudio::class, ['context' => $context, 'content' => $content])
            ->call('openMediaDialog', 'image')
            ->assertSet('mediaDialogOpen', true)
            ->assertSeeHtml('data-upload-limits')
            ->set('assetUpload', UploadedFile::fake()->image('board.png', 40, 30))
            ->set('assetRightsStatus', 'owned')
            ->call('attachAsset')
            ->assertHasNoErrors()
            ->assertSet('mediaDialogOpen', false);

        $draft = $content->fresh()->draftRevisionRecord();
        $media = $draft->blocks()->where('type', 'image')->sole();
        $placement = DB::table('space_content_revision_assets')
            ->where('space_content_revision_id', $draft->id)
            ->where('uuid', $media->data['asset_placement_uuid'])
            ->first();

        $this->assertNotNull($placement, 'The image block must reference a placement of the same revision.');
        $this->assertSame('board.png', $media->data['filename']);
    }

    public function test_media_survives_later_revisions_of_the_text(): void
    {
        Storage::fake('local');
        [$actor, $context, $content] = $this->lesson();

        $studio = Livewire::actingAs($actor->user)
            ->test(ContentStudio::class, ['context' => $context, 'content' => $content])
            ->call('openMediaDialog', 'image')
            ->set('assetUpload', UploadedFile::fake()->image('first.png'))
            ->call('attachAsset')
            ->set('payload.body', 'Changed after the image was placed')
            ->call('addBlock', 'paragraph')
            ->set('blocks.3.data.text', 'More text')
            ->call('saveRevision')
            ->assertHasNoErrors();

        $draft = $content->fresh()->draftRevisionRecord();
        $this->assertSame(['field', 'field', 'image', 'paragraph'], $draft->blocks()->pluck('type')->all());
        $placementUuid = $draft->blocks()->where('type', 'image')->sole()->data['asset_placement_uuid'];
        $this->assertTrue(DB::table('space_content_revision_assets')
            ->where('space_content_revision_id', $draft->id)
            ->where('uuid', $placementUuid)
            ->exists());

        // Removing the media removes its block too, without breaking the document.
        $assetId = $draft->assets()->sole()->id;
        $studio->call('removeAsset', $assetId)->assertHasNoErrors();
        $this->assertSame(['field', 'field', 'paragraph'], $content->fresh()->draftRevisionRecord()->blocks()->pluck('type')->all());
    }

    public function test_per_type_limit_is_enforced_with_a_clear_message(): void
    {
        Storage::fake('local');
        config()->set('media.limits.content_image', 10);
        [$actor, $context, $content] = $this->lesson();

        Livewire::actingAs($actor->user)
            ->test(ContentStudio::class, ['context' => $context, 'content' => $content])
            ->call('openMediaDialog', 'image')
            ->set('assetUpload', UploadedFile::fake()->image('big.png')->size(50))
            ->call('attachAsset')
            ->assertHasErrors('assetUpload');

        $this->assertSame(0, $content->fresh()->draftRevisionRecord()?->assets()->count() ?? 0);
    }

    public function test_unsupported_file_type_is_rejected_before_storage(): void
    {
        Storage::fake('local');
        [$actor, $context, $content] = $this->lesson();

        Livewire::actingAs($actor->user)
            ->test(ContentStudio::class, ['context' => $context, 'content' => $content])
            ->call('openMediaDialog', 'file')
            ->set('assetUpload', UploadedFile::fake()->create('script.exe', 5, 'application/x-msdownload'))
            ->call('attachAsset')
            ->assertHasErrors('assetUpload');
    }

    public function test_limits_are_semantic_and_livewire_temporary_uploads_allow_them(): void
    {
        $limits = MediaLimits::contentBytes();

        $this->assertGreaterThan($limits['image'], $limits['video']);
        $this->assertSame('content_video', MediaLimits::contentKindForMime('video/mp4'));
        $this->assertSame('content_document', MediaLimits::contentKindForMime('application/pdf'));

        $rule = collect(config('livewire.temporary_file_upload.rules'))->first(fn ($rule) => is_string($rule) && str_starts_with($rule, 'max:'));
        $this->assertNotNull($rule);
        $this->assertGreaterThanOrEqual((int) config('media.limits.content_video'), (int) substr($rule, 4));
    }

    /** @return array{Actor, Context, SpaceContent} */
    private function lesson(): array
    {
        $actor = Actor::factory()->create();
        $context = app(EnsurePersonalContext::class)->execute($actor->user);
        app(EnsureSystemContentBlueprints::class)->execute();
        $version = ContentBlueprint::query()->where('slug', 'lesson')->firstOrFail()->activeVersionRecord();
        $this->assertNotNull($version);

        return [$actor, $context, app(CreateContentFromBlueprint::class)->execute(
            $context, $version, $actor->user, 'Lesson with media', ['objective' => null, 'body' => 'Text'],
        )];
    }
}
