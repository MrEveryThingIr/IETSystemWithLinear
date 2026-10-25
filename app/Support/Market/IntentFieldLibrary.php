<?php

namespace App\Support\Market;

use Illuminate\Support\Facades\Lang;

/** Finds fields by what people type: localized labels, synonyms, domain names, even English in another language. */
final class IntentFieldLibrary
{
    /** Make Persian, Arabic and Latin spellings comparable. */
    public static function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = strtr($text, ['ي' => 'ی', 'ك' => 'ک', 'ة' => 'ه', 'ۀ' => 'ه', 'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', "\u{200c}" => ' ', 'ـ' => '']);
        $text = strtr($text, ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']);
        $text = preg_replace('/[\x{064B}-\x{065F}]/u', '', $text) ?? $text;

        return preg_replace('/\s+/u', ' ', $text) ?? $text;
    }

    /**
     * Keys matching the query, best first. An empty query matches nothing (callers show groups instead).
     *
     * @param  list<string>  $exclude
     * @return list<string>
     */
    public static function search(string $query, array $exclude = [], ?string $locale = null): array
    {
        $needle = self::normalize($query);
        if ($needle === '') {
            return [];
        }
        $locale ??= app()->getLocale();
        $ranked = [];
        foreach (IntentDetailCatalog::offered() as $key => $spec) {
            if (in_array($key, $exclude, true)) {
                continue;
            }
            $score = self::score($needle, $key, $spec, $locale);
            if ($score !== null) {
                $ranked[$key] = $score;
            }
        }
        asort($ranked);

        return array_keys($ranked);
    }

    /**
     * The whole library grouped by domain, labels sorted, minus already selected keys.
     *
     * @param  list<string>  $exclude
     * @return array<string, list<string>>
     */
    public static function grouped(array $exclude = []): array
    {
        $groups = [];
        foreach (IntentDetailCatalog::offered() as $key => $spec) {
            if (! in_array($key, $exclude, true)) {
                $groups[$spec['domain'] ?? 'general'][] = $key;
            }
        }
        $ordered = [];
        foreach (IntentDetailCatalog::DOMAINS as $domain) {
            if (isset($groups[$domain])) {
                usort($groups[$domain], fn (string $a, string $b): int => strcmp(IntentDetailCatalog::label($a), IntentDetailCatalog::label($b)));
                $ordered[$domain] = $groups[$domain];
            }
        }

        return $ordered;
    }

    /** @param array<string, mixed> $spec */
    private static function score(string $needle, string $key, array $spec, string $locale): ?int
    {
        $best = null;
        $consider = function (string $haystack, int $starts, int $contains) use ($needle, &$best): void {
            $haystack = self::normalize($haystack);
            if ($haystack === '') {
                return;
            }
            $rank = str_starts_with($haystack, $needle) ? $starts : (str_contains($haystack, $needle) ? $contains : null);
            if ($rank !== null && ($best === null || $rank < $best)) {
                $best = $rank;
            }
        };
        $consider(IntentDetailCatalog::label($key, $locale), 0, 1);
        foreach (IntentDetailCatalog::aliases($key, $locale) as $alias) {
            $consider($alias, 2, 3);
        }
        if ($locale !== 'en') {
            $consider(IntentDetailCatalog::label($key, 'en'), 4, 5);
            foreach (IntentDetailCatalog::aliases($key, 'en') as $alias) {
                $consider($alias, 6, 7);
            }
        }
        $domain = 'composer.domains.'.($spec['domain'] ?? 'general');
        if (Lang::has($domain, $locale, false)) {
            $consider((string) __($domain, [], $locale), 8, 8);
        }
        $consider($key, 9, 9);

        return $best;
    }
}
