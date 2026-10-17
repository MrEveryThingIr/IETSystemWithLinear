<?php

namespace App\Support\Media;

use App\Models\Actor;
use App\Models\Asset;
use App\Models\AssetMediaTrack;
use App\Support\ContextScope;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Captions and subtitles for audio/video Assets.
 *
 * Accepts WebVTT, or SRT which is converted to WebVTT (the only format
 * browsers play). Stored privately next to the Asset and served through the
 * same authorization as the media itself.
 */
final class CaptionTracks
{
    public const MAX_BYTES = 512 * 1024;

    public function store(Asset $asset, UploadedFile $file, string $language, string $label, Actor $actor): AssetMediaTrack
    {
        if (! in_array($asset->mediaKind(), ['video', 'audio'], true)) {
            throw ValidationException::withMessages(['captionUpload' => __('studio_ui.captions.only_media')]);
        }

        $size = (int) $file->getSize();
        if ($size <= 0 || $size > self::MAX_BYTES) {
            throw ValidationException::withMessages(['captionUpload' => __('studio_ui.captions.too_large')]);
        }

        $vtt = self::toWebVtt((string) file_get_contents((string) $file->getRealPath()));
        if ($vtt === null) {
            throw ValidationException::withMessages(['captionUpload' => __('studio_ui.captions.invalid')]);
        }

        $language = Str::lower(trim($language));
        if (preg_match('/^[a-z]{2,3}(-[a-z0-9]{2,8})?$/', $language) !== 1) {
            throw ValidationException::withMessages(['captionLanguage' => __('studio_ui.captions.invalid_language')]);
        }

        $asset->loadMissing('context');
        $directory = ($asset->context !== null ? ContextScope::storageSegment($asset->context) : 'assets').'/asset-variants';
        $key = $directory.'/'.Str::uuid().'.vtt';
        Storage::disk('local')->put($key, $vtt);

        return AssetMediaTrack::query()->create([
            'asset_id' => $asset->id,
            'kind' => 'captions',
            'language' => $language,
            'label' => mb_substr(trim($label) !== '' ? trim($label) : Str::upper($language), 0, 120),
            'disk' => 'local',
            'storage_key' => $key,
            'byte_size' => strlen($vtt),
            'is_default' => ! AssetMediaTrack::query()->where('asset_id', $asset->id)->exists(),
            'created_by_actor_id' => $actor->id,
        ]);
    }

    public function remove(AssetMediaTrack $track): void
    {
        Storage::disk($track->disk)->delete($track->storage_key);
        $track->delete();
    }

    /** Normalize WebVTT or SRT text to WebVTT. Null when it has no cues. */
    public static function toWebVtt(string $text): ?string
    {
        $text = (string) preg_replace('/^\xEF\xBB\xBF/', '', $text);
        if (! mb_check_encoding($text, 'UTF-8')) {
            $converted = @mb_convert_encoding($text, 'UTF-8', 'Windows-1256,ISO-8859-1');
            $text = is_string($converted) ? $converted : '';
        }
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        if (! str_starts_with(ltrim($text), 'WEBVTT')) {
            // SRT: "00:00:01,500 --> 00:00:03,000" becomes "00:00:01.500 --> 00:00:03.000".
            $text = (string) preg_replace('/(\d{1,2}:\d{2}:\d{2}),(\d{3})/', '$1.$2', $text);
            $text = "WEBVTT\n\n".trim($text)."\n";
        }

        if (preg_match('/\d{1,2}:\d{2}(?::\d{2})?\.\d{3}\s+-->\s+\d{1,2}:\d{2}(?::\d{2})?\.\d{3}/', $text) !== 1) {
            return null;
        }

        // Captions are plain text: drop any HTML/script that is not a WebVTT tag.
        $text = (string) preg_replace('#<(?!/?(?:b|i|u|c|v|lang|ruby|rt)\b)[^>]*>#i', '', $text);

        return $text;
    }
}
