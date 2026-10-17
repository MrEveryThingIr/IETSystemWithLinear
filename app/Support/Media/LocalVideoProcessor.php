<?php

namespace App\Support\Media;

use App\Contracts\Media\VideoProcessor;
use App\Models\Asset;
use App\Models\AssetMediaVariant;
use App\Support\ContextScope;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Shared-host video processing: no transcoding.
 *
 * - "original" variant: the immutable uploaded file with its duration and
 *   dimensions (measured by the browser, bounded and validated here).
 * - "poster" variant: a frame captured by the browser at upload time,
 *   stored privately next to the Asset.
 *
 * Playback streams the original MP4/WebM with byte ranges, so seeking works.
 */
final class LocalVideoProcessor implements VideoProcessor
{
    private const MAX_DURATION_MS = 12 * 60 * 60 * 1000;

    private const MAX_DIMENSION = 8192;

    private const MAX_POSTER_BYTES = 2 * 1024 * 1024;

    public function name(): string
    {
        return 'local';
    }

    public function process(Asset $asset, array $metadata, ?UploadedFile $poster): void
    {
        $kind = $asset->mediaKind();
        if (! in_array($kind, ['video', 'audio'], true)) {
            return;
        }

        $duration = $this->bounded($metadata['duration_ms'] ?? null, self::MAX_DURATION_MS);
        $width = $kind === 'video' ? $this->bounded($metadata['width'] ?? null, self::MAX_DIMENSION) : null;
        $height = $kind === 'video' ? $this->bounded($metadata['height'] ?? null, self::MAX_DIMENSION) : null;

        AssetMediaVariant::query()->updateOrCreate(
            ['asset_id' => $asset->id, 'type' => AssetMediaVariant::TYPE_ORIGINAL, 'profile' => 'default'],
            [
                'width' => $width,
                'height' => $height,
                'duration_ms' => $duration,
                'container' => $asset->extension,
                'mime_type' => $asset->mime_type,
                'disk' => $asset->disk,
                'storage_key' => $asset->storage_key,
                'byte_size' => $asset->byte_size,
                'processing_status' => 'ready',
            ],
        );

        if ($kind === 'video' && $poster instanceof UploadedFile) {
            $this->storePoster($asset, $poster, $width, $height);
        }
    }

    private function storePoster(Asset $asset, UploadedFile $poster, ?int $width, ?int $height): void
    {
        $mime = (string) $poster->getMimeType();
        $size = (int) $poster->getSize();
        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) || $size <= 0 || $size > self::MAX_POSTER_BYTES) {
            return;
        }

        $asset->loadMissing('context');
        $extension = match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };
        $directory = ($asset->context !== null ? ContextScope::storageSegment($asset->context) : 'assets').'/asset-variants';
        $key = $poster->storeAs($directory, (string) Str::uuid().'.'.$extension, 'local');
        if (! is_string($key)) {
            return;
        }

        $previous = AssetMediaVariant::query()
            ->where('asset_id', $asset->id)
            ->where('type', AssetMediaVariant::TYPE_POSTER)
            ->where('profile', 'default')
            ->first();

        AssetMediaVariant::query()->updateOrCreate(
            ['asset_id' => $asset->id, 'type' => AssetMediaVariant::TYPE_POSTER, 'profile' => 'default'],
            [
                'width' => $width,
                'height' => $height,
                'mime_type' => $mime,
                'disk' => 'local',
                'storage_key' => $key,
                'byte_size' => $size,
                'processing_status' => 'ready',
            ],
        );

        if ($previous !== null && $previous->storage_key !== null && $previous->storage_key !== $key) {
            Storage::disk('local')->delete($previous->storage_key);
        }
    }

    private function bounded(mixed $value, int $max): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        $int = (int) round((float) $value);

        return $int > 0 && $int <= $max ? $int : null;
    }
}
