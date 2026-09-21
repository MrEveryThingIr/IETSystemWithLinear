<?php

namespace App\Support;

class Localization
{
    /**
     * @return array<string, array{
     *     name: string,
     *     native_name: string,
     *     direction: string,
     *     intl_locale?: string,
     *     default_calendar?: string,
     *     first_day_of_week?: int
     * }>
     */
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

    public static function intlLocale(?string $locale = null): string
    {
        $code = $locale ?? app()->getLocale();
        $intl = self::supported()[$code]['intl_locale'] ?? str_replace('_', '-', $code);

        return $intl !== '' ? $intl : 'en';
    }

    public static function defaultCalendar(?string $locale = null): string
    {
        $calendar = self::supported()[$locale ?? app()->getLocale()]['default_calendar'] ?? 'gregory';

        return $calendar !== '' ? $calendar : 'gregory';
    }

    public static function firstDayOfWeek(?string $locale = null): int
    {
        $day = self::supported()[$locale ?? app()->getLocale()]['first_day_of_week'] ?? 1;
        return min(7, max(1, $day));
    }
}
