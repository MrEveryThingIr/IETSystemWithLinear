<?php

namespace App\Support;

class Localization
{
    /** @return array<string, array{name: string, native_name: string, direction: string}> */
    public static function supported(): array
    {
        $locales = config('localization.locales', []);

        return is_array($locales) ? $locales : [];
    }

    /** @return list<string> */
    public static function codes(): array
    {
        return array_keys(self::supported());
    }

    public static function supports(?string $locale): bool
    {
        return $locale !== null && array_key_exists($locale, self::supported());
    }

    public static function direction(?string $locale = null): string
    {
        $direction = self::supported()[$locale ?? app()->getLocale()]['direction'] ?? 'ltr';

        return $direction === 'rtl' ? 'rtl' : 'ltr';
    }
}
