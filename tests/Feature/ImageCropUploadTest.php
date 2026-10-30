<?php

namespace Tests\Feature;

use App\Actions\Content\CreateContentFromBlueprint;
use App\Actions\Content\EnsureSystemContentBlueprints;
use App\Actions\Contexts\EnsurePersonalContext;
use App\Actions\Groups\PublishSpaceContent;
use App\Livewire\Contexts\ContentStudio;
use App\Models\Actor;
use App\Models\Asset;
use App\Models\ContentBlueprint;
use App\Models\Context;
use App\Models\SpaceContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Cropping happens in the browser (resources/js/image-crop.js, unit tested with `npm run test:js`). The picture it
 * produces is the file that is uploaded, so the server sees an ordinary image upload: these tests prove that path is one
 * upload, one Asset, one placement, in every state, and that cropped dimensions, captions and the published reader hold.
 */
class ImageCropUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    /** @return array{0: Actor, 1: Context, 2: SpaceContent} */
    private function lesson(): array
    {
        $actor = Actor::factory()->create();
        $context = app(EnsurePersonalContext::class)->execute($actor->user);
        app(EnsureSystemContentBlueprints::class)->execute();
        $version = ContentBlueprint::query()->where('slug', 'lesson')->firstOrFail()->activeVersionRecord();

        return [$actor, $context, app(CreateContentFromBlueprint::class)->execute($context, $version, $actor->user, 'Garden', ['objective' => null, 'body' => 'Text'])];
    }

    private function studio(Actor $actor, Context $context, SpaceContent $content)
    {
        return Livewire::actingAs($actor->user)->test(ContentStudio::class, ['context' => $context, 'content' => $content])->call('openMediaDialog', 'image');
    }

    /** @return array{0: int, 1: int} */
    private function storedSize(Asset $asset): array
    {
        $info = getimagesizefromstring(Storage::disk($asset->disk)->get($asset->storage_key));

        return [$info[0], $info[1]];
    }

    public function test_an_image_without_a_crop_uploads_exactly_as_before(): void
    {
        [$actor, $context, $content] = $this->lesson();

        $this->studio($actor, $context, $content)
            ->set('assetUpload', UploadedFile::fake()->image('IMG_2041.jpg', 1200, 900))->set('assetRightsStatus', 'owned')->set('assetCaption', 'The whole garden')
            ->call('attachAsset')->assertHasNoErrors();

        $asset = Asset::query()->sole();
        $this->assertSame('IMG_2041.jpg', $asset->original_filename);
        $this->assertSame([1200, 900], $this->storedSize($asset));
        $this->assertSame(1, $content->fresh()->draftRevisionRecord()->blocks()->where('type', 'image')->count());
    }

    public function test_a_cropped_portrait_is_the_stored_original_with_its_cropped_dimensions_and_keeps_its_caption(): void
    {
        [$actor, $context, $content] = $this->lesson();

        // What the browser produces for a 3024 x 4032 phone portrait cropped to its middle: upright, portrait, smaller.
        $this->studio($actor, $context, $content)
            ->set('assetUpload', UploadedFile::fake()->image('IMG_2041-cropped.jpg', 900, 1200))->set('assetRightsStatus', 'owned')->set('assetCaption', 'The old gate')
            ->call('attachAsset')->assertHasNoErrors();

        $asset = Asset::query()->sole();
        [$width, $height] = $this->storedSize($asset);
        $this->assertSame([900, 1200], [$width, $height], 'the portrait stays portrait and keeps the cropped size');
        $this->assertSame(0, DB::table('asset_media_variants')->count(), 'no derivative is kept: the cropped picture is the original');

        $draft = $content->fresh()->draftRevisionRecord();
        $block = $draft->blocks()->where('type', 'image')->sole();
        $this->assertSame('IMG_2041-cropped.jpg', $block->data['filename']);
        $this->assertSame('The old gate', DB::table('space_content_revision_assets')->where('space_content_revision_id', $draft->id)->where('asset_id', $asset->id)->value('caption'));
    }

    public function test_finalizing_twice_or_retrying_never_duplicates_the_asset_or_placement(): void
    {
        [$actor, $context, $content] = $this->lesson();
        $studio = $this->studio($actor, $context, $content)
            ->set('assetUpload', UploadedFile::fake()->image('gate-cropped.jpg', 640, 480))->set('assetRightsStatus', 'owned');

        $studio->call('attachAsset')->assertHasNoErrors();
        // A repeated click or a retried request finds nothing left to attach.
        $studio->call('attachAsset')->assertHasErrors('assetUpload');

        $this->assertSame(1, Asset::query()->count());
        $draft = $content->fresh()->draftRevisionRecord();
        $this->assertSame(1, DB::table('space_content_revision_assets')->where('space_content_revision_id', $draft->id)->count());
        $this->assertSame(1, $draft->blocks()->where('type', 'image')->count());
    }

    public function test_a_cropped_image_renders_in_the_published_reader_for_its_owner(): void
    {
        [$actor, $context, $content] = $this->lesson();
        $this->studio($actor, $context, $content)
            ->set('assetUpload', UploadedFile::fake()->image('gate-cropped.png', 500, 500))->set('assetRightsStatus', 'owned')->call('attachAsset')->assertHasNoErrors();

        $published = app(PublishSpaceContent::class)->execute($content->fresh(), $actor->user);
        $asset = $published->activeRevisionRecord()->assets()->sole();

        $this->actingAs($actor->user)->get(route('contexts.contents.show', [$context, $published]))->assertOk()->assertSee('<img', false);
        $response = $this->actingAs($actor->user)->get(route('contexts.contents.assets.show', [$context, $published, $asset]));
        $response->assertOk();
        $this->assertStringStartsWith('image/', (string) $response->headers->get('Content-Type'));
    }

    public function test_the_studio_offers_a_preview_and_an_optional_crop_in_the_existing_upload_dialog(): void
    {
        [$actor, $context, $content] = $this->lesson();

        $this->studio($actor, $context, $content)
            ->assertSeeHtml('data-image-preview')->assertSeeHtml('data-crop-start')->assertSeeHtml('data-crop-apply')
            ->assertSeeHtml('data-use-image')->assertSeeHtml('data-crop-handle="se"')->assertSeeHtml('data-upload-input');
    }
}
