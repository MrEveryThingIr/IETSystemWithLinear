<?php

namespace App\Support\Media;

use App\Models\Asset;
use App\Models\AssetMediaVariant;
use App\Support\ContextScope;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Makes "fast start" playback copies for MP4/MOV files uploaded before
 * uploads were optimized. The original file (and its hash) is never touched;
 * playback uses the copy, downloads keep the original. Each video is checked
 * once: videos that already start quickly are only marked as checked.
 */
final class FaststartExistingVideos
{
    public const PROFILE = 'faststart';

    /** @return array{checked: int, optimized: int, failed: int} */
    public function run(int $limit = 50): array
    {
        $result = ['checked' => 0, 'optimized' => 0, 'failed' => 0];

        $assets = Asset::query()
            ->whereIn('mime_type', Mp4Faststart::MIME_TYPES)
            ->whereNotExists(fn ($query) => $query->selectRaw('1')
                ->from('asset_media_variants')
                ->whereColumn('asset_media_variants.asset_id', 'assets.id')
                ->where('asset_media_variants.type', AssetMediaVariant::TYPE_MP4)
                ->where('asset_media_variants.profile', self::PROFILE))
            ->with('context')
            ->orderBy('id')
            ->limit(max(1, $limit))
            ->get();

        foreach ($assets as $asset) {
            $result['checked']++;
            $disk = Storage::disk($asset->disk);

            try {
                if (! $disk->exists($asset->storage_key)) {
                    $this->mark($asset, 'missing');

                    continue;
                }

                $source = $disk->path($asset->storage_key);
                if (Mp4Faststart::isFaststart($source)) {
                    $this->mark($asset, 'not_needed');

                    continue;
                }

                $key = ContextScope::storageSegment($asset->context).'/playback/'.$asset->uuid.'.mp4';
                $disk->makeDirectory(dirname($key));
                $target = $disk->path($key);
                copy($source, $target);

                if (! Mp4Faststart::optimize($target)) {
                    @unlink($target);
                    $this->mark($asset, 'unsupported');

                    continue;
                }

                AssetMediaVariant::query()->create([
                    'asset_id' => $asset->id,
                    'type' => AssetMediaVariant::TYPE_MP4,
                    'profile' => self::PROFILE,
                    'container' => 'mp4',
                    'mime_type' => $asset->mime_type,
                    'disk' => $asset->disk,
                    'storage_key' => $key,
                    'byte_size' => (int) filesize($target),
                    'processing_status' => 'ready',
                ]);
                $result['optimized']++;
            } catch (Throwable $exception) {
                report($exception);
                $result['failed']++;
            }
        }

        return $result;
    }

    private function mark(Asset $asset, string $status): void
    {
        AssetMediaVariant::query()->create([
            'asset_id' => $asset->id,
            'type' => AssetMediaVariant::TYPE_MP4,
            'profile' => self::PROFILE,
            'mime_type' => $asset->mime_type,
            'disk' => $asset->disk,
            'storage_key' => null,
            'processing_status' => $status,
        ]);
    }
}
