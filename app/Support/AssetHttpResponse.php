<?php

namespace App\Support;

use App\Models\Asset;
use App\Models\AssetMediaVariant;
use App\Support\Media\Mp4Faststart;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams an already-authorized Asset to the browser.
 *
 * Local disks are served as BinaryFileResponse so browsers receive
 * Accept-Ranges/206 support (video and audio seeking). Other disks fall back to
 * the filesystem's streamed response. Callers must authorize before calling.
 */
final class AssetHttpResponse
{
    /**
     * Asset files never change once stored, so the browser may keep them for
     * a day (privately) and revalidate with the content hash. Replays and
     * seeks in a video then reuse what was already downloaded instead of
     * fetching it again over a slow connection.
     *
     * @return array<string, string>
     */
    private static function headers(Asset $asset): array
    {
        return [
            'Content-Type' => $asset->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=86400',
        ];
    }

    public static function inline(Asset $asset): Response
    {
        $playback = self::playbackCopy($asset);

        return $playback !== null
            ? self::respondFile($asset, $playback, HeaderUtils::DISPOSITION_INLINE)
            : self::respond($asset, HeaderUtils::DISPOSITION_INLINE);
    }

    /**
     * Older MP4 uploads may have a "fast start" copy (index first) made by
     * `media:faststart-existing`; playback uses it, downloads keep the original.
     */
    private static function playbackCopy(Asset $asset): ?AssetMediaVariant
    {
        if (! in_array(strtolower((string) $asset->mime_type), Mp4Faststart::MIME_TYPES, true)) {
            return null;
        }

        $variant = AssetMediaVariant::query()
            ->where('asset_id', $asset->id)
            ->where('type', AssetMediaVariant::TYPE_MP4)
            ->where('profile', 'faststart')
            ->where('processing_status', 'ready')
            ->whereNotNull('storage_key')
            ->latest('id')
            ->first();

        return $variant !== null && Storage::disk((string) $variant->disk)->exists((string) $variant->storage_key) ? $variant : null;
    }

    private static function respondFile(Asset $asset, AssetMediaVariant $variant, string $disposition): Response
    {
        $response = new BinaryFileResponse(
            Storage::disk((string) $variant->disk)->path((string) $variant->storage_key),
            200,
            self::headers($asset),
            false,
        );
        $response->setContentDisposition($disposition, $asset->original_filename, self::fallbackName($asset));

        return self::conditional($asset, $response);
    }

    private static function conditional(Asset $asset, Response $response): Response
    {
        if ($asset->sha256 !== '') {
            $response->setEtag($asset->sha256);
            $request = app()->bound('request') ? request() : null;
            if ($request !== null && $response->isNotModified($request)) {
                return $response;
            }
        }

        return $response;
    }

    public static function download(Asset $asset): Response
    {
        return self::respond($asset, HeaderUtils::DISPOSITION_ATTACHMENT);
    }

    private static function respond(Asset $asset, string $disposition): Response
    {
        $disk = Storage::disk($asset->disk);
        $driver = config("filesystems.disks.{$asset->disk}.driver");

        if ($driver === 'local') {
            $response = new BinaryFileResponse(
                $disk->path($asset->storage_key),
                200,
                self::headers($asset),
                false,
            );
            $response->setContentDisposition($disposition, $asset->original_filename, self::fallbackName($asset));

            return self::conditional($asset, $response);
        }

        /** @var StreamedResponse $streamed */
        $streamed = $disposition === HeaderUtils::DISPOSITION_INLINE
            ? $disk->response($asset->storage_key, $asset->original_filename, self::headers($asset), 'inline')
            : $disk->download($asset->storage_key, $asset->original_filename, self::headers($asset));

        return $streamed;
    }

    private static function fallbackName(Asset $asset): string
    {
        $ascii = preg_replace('/[^\x20-\x7E]|[\/\\\\%"]/', '_', $asset->original_filename) ?? 'file';

        return trim($ascii) !== '' ? $ascii : 'file';
    }
}
