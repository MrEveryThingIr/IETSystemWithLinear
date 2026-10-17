<?php

namespace App\Support\PublicContent;

use App\Models\Asset;
use App\Models\SpaceContentRevision;
use Illuminate\Support\Str;

/** A short plain-text teaser and cover image of a published edition. */
final class ContentExcerpt
{
    private const PREFERRED_FIELDS = ['summary', 'objective', 'description', 'body', 'text', 'notes'];

    public static function text(SpaceContentRevision $revision, int $limit = 220): string
    {
        $payload = $revision->payload;

        foreach (self::PREFERRED_FIELDS as $key) {
            if (is_string($payload[$key] ?? null) && trim($payload[$key]) !== '') {
                return self::clean($payload[$key], $limit);
            }
        }

        if ($revision->composition_mode === SpaceContentRevision::COMPOSITION_BLOCKS) {
            foreach ($revision->blocks()->whereIn('type', ['paragraph', 'quote', 'callout'])->limit(3)->get() as $block) {
                $text = $block->data['text'] ?? null;
                if (is_string($text) && trim($text) !== '') {
                    return self::clean($text, $limit);
                }
            }
        }

        foreach ($payload as $value) {
            if (is_string($value) && mb_strlen(trim($value)) > 20) {
                return self::clean($value, $limit);
            }
        }

        return '';
    }

    public static function cover(SpaceContentRevision $revision): ?Asset
    {
        $revision->loadMissing('assets');

        return $revision->assets->first(fn (Asset $asset): bool => $asset->mediaKind() === 'image');
    }

    private static function clean(string $text, int $limit): string
    {
        return Str::limit(trim((string) preg_replace('/\s+/u', ' ', strip_tags($text))), $limit);
    }
}
