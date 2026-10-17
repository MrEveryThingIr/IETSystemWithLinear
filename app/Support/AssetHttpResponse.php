<?php

namespace App\Support;

use App\Models\Asset;
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
    /** @return array<string, string> */
    private static function headers(Asset $asset): array
    {
        return [
            'Content-Type' => $asset->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ];
    }

    public static function inline(Asset $asset): Response
    {
        return self::respond($asset, HeaderUtils::DISPOSITION_INLINE);
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

            return $response;
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
