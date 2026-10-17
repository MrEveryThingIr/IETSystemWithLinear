<?php

namespace Tests\Feature;

use App\Actions\Content\CreateContentFromBlueprint;
use App\Actions\Content\EnsureSystemContentBlueprints;
use App\Actions\Contexts\EnsurePersonalContext;
use App\Actions\Groups\AttachAssetToSpaceContent;
use App\Actions\Groups\PublishSpaceContent;
use App\Actions\Groups\UpdateSpaceContentStructure;
use App\Contracts\Media\VideoProcessor;
use App\Livewire\Contexts\ContentShow;
use App\Livewire\Contexts\ContentStudio;
use App\Models\Actor;
use App\Models\Asset;
use App\Models\AssetMediaTrack;
use App\Models\AssetMediaVariant;
use App\Models\ContentBlueprint;
use App\Models\Context;
use App\Models\SpaceContent;
use App\Support\Media\CaptionTracks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\PublishesFeatureSurfaces;
use Tests\TestCase;

class EducationalVideoProcessingTest extends TestCase
{
    use PublishesFeatureSurfaces;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_lesson_video_streams_with_byte_ranges_so_seeking_works(): void
    {
        [$actor, $context, $content, $asset] = $this->lessonWithVideo();

        $response = $this->actingAs($actor->user)
            ->get(route('contexts.contents.assets.show', [$context, $content, $asset]), ['Range' => 'bytes=0-9']);

        $response->assertStatus(206)
            ->assertHeader('Accept-Ranges', 'bytes')
            ->assertHeader('Content-Type', 'video/mp4');
        $this->assertStringStartsWith('bytes 0-9/', (string) $response->headers->get('Content-Range'));
    }

    public function test_poster_and_captions_are_private_derivatives_served_with_content_authorization(): void
    {
        [$actor, $context, $content, $asset] = $this->lessonWithVideo();
        app(VideoProcessor::class)->process($asset, ['duration_ms' => 2000, 'width' => 96, 'height' => 64], UploadedFile::fake()->image('poster.jpg', 96, 64));
        $poster = AssetMediaVariant::query()->where('asset_id', $asset->id)->where('type', 'poster')->sole();

        Livewire::actingAs($actor->user)
            ->test(ContentStudio::class, ['context' => $context, 'content' => $content])
            ->call('openCaptions', $asset->id)
            ->assertSeeHtml('data-studio-captions-dialog')
            ->set('captionUpload', UploadedFile::fake()->createWithContent('lesson.srt', "1\n00:00:00,000 --> 00:00:01,500\nHello class\n"))
            ->set('captionLanguage', 'en')
            ->set('captionLabel', 'English')
            ->call('addCaptions')
            ->assertHasNoErrors();

        $track = AssetMediaTrack::query()->where('asset_id', $asset->id)->sole();
        $this->assertTrue($track->is_default);
        $this->assertStringContainsString('00:00:00.000 --> 00:00:01.500', Storage::disk('local')->get($track->storage_key));

        $this->actingAs($actor->user)
            ->get(route('contexts.contents.assets.variants.show', [$context, $content, $asset, $poster]))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');
        $vtt = $this->actingAs($actor->user)
            ->get(route('contexts.contents.assets.tracks.show', [$context, $content, $asset, $track]));
        $vtt->assertOk();
        $this->assertStringStartsWith('text/vtt', (string) $vtt->headers->get('Content-Type'));

        $stranger = Actor::factory()->create();
        $this->publishSurfaces($stranger->user, ['content']);
        $this->actingAs($stranger->user)
            ->get(route('contexts.contents.assets.variants.show', [$context, $content, $asset, $poster]))
            ->assertForbidden();
        $this->actingAs($stranger->user)
            ->get(route('contexts.contents.assets.tracks.show', [$context, $content, $asset, $track]))
            ->assertForbidden();

        // The published lesson plays with its poster, captions and speed control.
        $content = app(PublishSpaceContent::class)->execute($content->fresh(), $actor->user);
        Livewire::actingAs($actor->user)
            ->test(ContentShow::class, ['context' => $context, 'content' => $content])
            ->assertSeeHtml('data-video-player')
            ->assertSeeHtml('poster="'.route('contexts.contents.assets.variants.show', [$context, $content, $asset, $poster]).'"')
            ->assertSeeHtml('<track kind="captions"')
            ->assertSeeHtml('srclang="en"')
            ->assertSeeHtml('data-player-speed');
    }

    public function test_captions_accept_webvtt_convert_srt_and_reject_other_text(): void
    {
        $this->assertSame("WEBVTT\n\n00:00:01.000 --> 00:00:02.000\nHi\n", CaptionTracks::toWebVtt("WEBVTT\n\n00:00:01.000 --> 00:00:02.000\nHi\n"));
        $this->assertStringContainsString('00:00:01.250 --> 00:00:02.000', (string) CaptionTracks::toWebVtt("1\r\n00:00:01,250 --> 00:00:02,000\r\nسلام\r\n"));
        $this->assertNull(CaptionTracks::toWebVtt('just some notes without cues'));
        $this->assertStringNotContainsString('<script', (string) CaptionTracks::toWebVtt("1\n00:00:01,000 --> 00:00:02,000\n<script>alert(1)</script><i>ok</i>\n"));
    }

    public function test_metadata_from_the_browser_is_bounded(): void
    {
        [, , , $asset] = $this->lessonWithVideo();

        app(VideoProcessor::class)->process($asset, ['duration_ms' => -5, 'width' => 999999, 'height' => 'abc'], null);

        $original = AssetMediaVariant::query()->where('asset_id', $asset->id)->where('type', 'original')->sole();
        $this->assertNull($original->duration_ms);
        $this->assertNull($original->width);
        $this->assertNull($original->height);
        $this->assertSame($asset->storage_key, $original->storage_key);
    }

    public function test_course_module_lesson_structure_gives_lessons_previous_and_next(): void
    {
        $actor = Actor::factory()->create();
        $context = app(EnsurePersonalContext::class)->execute($actor->user);
        $publish = app(PublishSpaceContent::class);

        $first = $publish->execute($this->make($actor, $context, 'lesson', 'Lesson 1', ['objective' => null, 'body' => 'One']), $actor->user);
        $second = $publish->execute($this->make($actor, $context, 'lesson', 'Lesson 2', ['objective' => null, 'body' => 'Two']), $actor->user);

        $module = $this->make($actor, $context, 'course-module', 'Module 1', ['summary' => null]);
        $module = app(UpdateSpaceContentStructure::class)->execute($module, $actor->user, [$first->id, $second->id]);
        $module = $publish->execute($module, $actor->user);

        $course = $this->make($actor, $context, 'course', 'English Course', ['summary' => 'A1 to B1']);
        $course = app(UpdateSpaceContentStructure::class)->execute($course, $actor->user, [$module->id]);
        $publish->execute($course, $actor->user);

        Livewire::actingAs($actor->user)
            ->test(ContentShow::class, ['context' => $context, 'content' => $first->fresh()])
            ->assertSeeHtml('data-reader-course')
            ->assertSee('Module 1')
            ->assertSeeHtml('data-reader-course-nav')
            ->assertSee('Lesson 2');

        Livewire::actingAs($actor->user)
            ->test(ContentShow::class, ['context' => $context, 'content' => $second->fresh()])
            ->assertSee('Lesson 1')
            ->assertSee(__('reader.previous'));
    }

    /** @return array{Actor, Context, SpaceContent, Asset} */
    private function lessonWithVideo(): array
    {
        $actor = Actor::factory()->create();
        $this->publishSurfaces($actor->user, ['content']);
        $context = app(EnsurePersonalContext::class)->execute($actor->user);
        $content = $this->make($actor, $context, 'lesson', 'Video lesson', ['objective' => null, 'body' => 'Watch.']);

        $content = app(AttachAssetToSpaceContent::class)->execute(
            $content,
            $actor->user,
            new UploadedFile(base_path('tests/Fixtures/media/lesson-clip.mp4'), 'lesson-clip.mp4', 'video/mp4', null, true),
            'owned',
        );
        $asset = $content->draftRevisionRecord()->assets()->sole();

        return [$actor, $context, $content, $asset];
    }

    /** @param array<string, mixed> $payload */
    private function make(Actor $actor, Context $context, string $slug, string $title, array $payload): SpaceContent
    {
        app(EnsureSystemContentBlueprints::class)->execute();
        $version = ContentBlueprint::query()->where('slug', $slug)->firstOrFail()->activeVersionRecord();
        $this->assertNotNull($version);

        return app(CreateContentFromBlueprint::class)->execute($context, $version, $actor->user, $title, $payload);
    }
}
