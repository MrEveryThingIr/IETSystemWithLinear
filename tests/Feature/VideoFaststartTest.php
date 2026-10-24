<?php

namespace Tests\Feature;

use App\Actions\Content\CreateContentFromBlueprint;
use App\Actions\Content\EnsureSystemContentBlueprints;
use App\Actions\Contexts\EnsurePersonalContext;
use App\Actions\Groups\AttachAssetToSpaceContent;
use App\Models\Actor;
use App\Models\AssetMediaVariant;
use App\Models\ContentBlueprint;
use App\Models\Context;
use App\Models\SpaceContent;
use App\Support\Media\FaststartExistingVideos;
use App\Support\Media\Mp4Faststart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\PublishesFeatureSurfaces;
use Tests\TestCase;

/**
 * Videos whose index sits at the end start slowly on far-away connections.
 * Uploads are rewritten index-first before hashing; older files get a
 * playback copy. The media bytes never change.
 */
class VideoFaststartTest extends TestCase
{
    use PublishesFeatureSurfaces;
    use RefreshDatabase;

    private const FIXTURE = 'tests/Fixtures/media/moov-at-end.mp4';

    public function test_separate_output_preserves_the_original_and_cleans_temporary_files(): void
    {
        $source = $this->scratchCopy();
        $target = $source.'.playback.mp4';
        $checksum = hash_file('sha256', $source);
        try {
            $this->assertTrue(Mp4Faststart::optimize($source, $target));
            $this->assertSame($checksum, hash_file('sha256', $source));
            $this->assertTrue(Mp4Faststart::isFaststart($target));
            $this->assertSame([], glob($target.'.faststart-*'));
        } finally {
            @unlink($source);
            @unlink($target);
        }
    }

    public function test_replacement_failure_preserves_original_and_cleans_output(): void
    {
        $source = $this->scratchCopy();
        $target = $source.'.directory';
        mkdir($target);
        $checksum = hash_file('sha256', $source);
        try {
            try {
                Mp4Faststart::optimize($source, $target);
                $this->fail('Replacing a directory must fail.');
            } catch (\Throwable $exception) {
                $this->assertStringContainsString('replace', $exception->getMessage());
            }
            $this->assertSame($checksum, hash_file('sha256', $source));
            $this->assertSame([], glob($target.'.faststart-*'));
        } finally {
            @unlink($source);
            rmdir($target);
        }
    }

    public function test_index_moves_to_the_front_and_media_bytes_stay_identical(): void
    {
        $path = $this->scratchCopy();
        $this->assertFalse(Mp4Faststart::isFaststart($path));
        $before = $this->boxes($path);

        $this->assertTrue(Mp4Faststart::optimize($path));
        $this->assertTrue(Mp4Faststart::isFaststart($path));
        $after = $this->boxes($path);

        $this->assertSame(filesize(base_path(self::FIXTURE)), filesize($path));
        $this->assertSame(['ftyp', 'moov', 'free', 'mdat'], array_column($after, 'type'));
        $this->assertSame($this->payload(base_path(self::FIXTURE), $before, 'mdat'), $this->payload($path, $after, 'mdat'));
        $this->assertSame($this->payload(base_path(self::FIXTURE), $before, 'moov') !== '', true);

        // Running again changes nothing.
        $this->assertFalse(Mp4Faststart::optimize($path));
        @unlink($path);
    }

    public function test_uploaded_videos_are_stored_index_first_and_hashed_after(): void
    {
        Storage::fake('local');
        [$actor, $content] = $this->lesson();

        $content = app(AttachAssetToSpaceContent::class)->execute(
            $content,
            $actor->user,
            new UploadedFile($this->scratchCopy(), 'phone-video.mp4', 'video/mp4', null, true),
            'owned',
        );
        $asset = $content->draftRevisionRecord()->assets()->sole();
        $stored = Storage::disk('local')->path($asset->storage_key);

        $this->assertTrue(Mp4Faststart::isFaststart($stored));
        $this->assertSame(hash_file('sha256', $stored), $asset->sha256);

        // Files outside upload scratch space are never rewritten.
        $this->assertFalse(Mp4Faststart::optimizeUpload(base_path(self::FIXTURE), 'video/mp4'));
        $this->assertFalse(Mp4Faststart::isFaststart(base_path(self::FIXTURE)));
    }

    public function test_older_videos_get_a_playback_copy_and_browsers_can_reuse_what_they_have(): void
    {
        Storage::fake('local');
        [$actor, $content, $context] = $this->lesson();

        $content = app(AttachAssetToSpaceContent::class)->execute(
            $content,
            $actor->user,
            new UploadedFile(base_path('tests/Fixtures/media/lesson-clip.mp4'), 'clip.mp4', 'video/mp4', null, true),
            'owned',
        );
        $asset = $content->draftRevisionRecord()->assets()->sole();
        // Simulate a file uploaded before this release: index at the end.
        Storage::disk('local')->put($asset->storage_key, (string) file_get_contents(base_path(self::FIXTURE)));
        $original = Storage::disk('local')->get($asset->storage_key);

        $result = app(FaststartExistingVideos::class)->run();
        $this->assertSame(['checked' => 1, 'optimized' => 1, 'failed' => 0], $result, (string) AssetMediaVariant::query()->where('asset_id', $asset->id)->value('processing_status'));
        $this->assertSame(['checked' => 0, 'optimized' => 0, 'failed' => 0], app(FaststartExistingVideos::class)->run());
        $this->assertSame($original, Storage::disk('local')->get($asset->storage_key), 'The original is never touched.');

        $variant = AssetMediaVariant::query()->where('asset_id', $asset->id)->where('profile', 'faststart')->sole();
        $this->assertSame('ready', $variant->processing_status);

        $inline = $this->actingAs($actor->user)->get(route('contexts.contents.assets.show', [$context, $content, $asset]));
        $inline->assertOk()->assertHeader('Cache-Control', 'max-age=86400, private');
        $this->assertTrue(Mp4Faststart::isFaststart($inline->baseResponse->getFile()->getPathname()));
        $etag = (string) $inline->headers->get('ETag');
        $this->assertStringContainsString($asset->sha256, $etag);

        $this->actingAs($actor->user)
            ->get(route('contexts.contents.assets.show', [$context, $content, $asset]), ['If-None-Match' => $etag])
            ->assertStatus(304);

        $download = $this->actingAs($actor->user)->get(route('contexts.contents.assets.download', [$context, $content, $asset]));
        $this->assertSame(Storage::disk('local')->path($asset->storage_key), $download->baseResponse->getFile()->getPathname());
    }

    /** @return array{Actor, SpaceContent, Context} */
    private function lesson(): array
    {
        $actor = Actor::factory()->create();
        $this->publishSurfaces($actor->user, ['content']);
        $context = app(EnsurePersonalContext::class)->execute($actor->user);
        app(EnsureSystemContentBlueprints::class)->execute();
        $version = ContentBlueprint::query()->where('slug', 'lesson')->firstOrFail()->activeVersionRecord();

        $content = app(CreateContentFromBlueprint::class)->execute($context, $version, $actor->user, 'Video lesson', ['objective' => null, 'body' => 'Watch.']);

        return [$actor, $content, $context];
    }

    private function scratchCopy(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'mp4');
        copy(base_path(self::FIXTURE), $path);

        return $path;
    }

    /** @return list<array{type: string, offset: int, size: int}> */
    private function boxes(string $path): array
    {
        $data = (string) file_get_contents($path);
        $boxes = [];
        for ($offset = 0; $offset < strlen($data);) {
            $size = unpack('N', substr($data, $offset, 4))[1];
            $boxes[] = ['type' => substr($data, $offset + 4, 4), 'offset' => $offset, 'size' => $size];
            $offset += $size;
        }

        return $boxes;
    }

    /** @param list<array{type: string, offset: int, size: int}> $boxes */
    private function payload(string $path, array $boxes, string $type): string
    {
        foreach ($boxes as $box) {
            if ($box['type'] === $type) {
                return substr((string) file_get_contents($path), $box['offset'] + 8, $box['size'] - 8);
            }
        }

        return '';
    }
}
