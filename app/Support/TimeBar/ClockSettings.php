<?php

namespace App\Support\TimeBar;

use App\Models\User;
use App\Support\Localization;
use App\Support\TemporalPreferences;

/**
 * How the live clocks show time for the current viewer: the page language,
 * the person's own calendar (Persian, Gregorian, ...), time zone and 12/24h
 * choice. A guest sees the language's calendar in their device's time zone.
 */
final class ClockSettings
{
    /** @return array{locale: string, calendar: string, timezone: string, hour_cycle: string, inline_gregorian: bool} */
    public static function for(?User $user): array
    {
        $locale = app()->getLocale();

        return [
            'locale' => Localization::intlLocale($locale),
            'calendar' => TemporalPreferences::calendarFor($user, $locale)->value,
            // Empty: the browser uses the device's own time zone.
            'timezone' => $user instanceof User ? TemporalPreferences::timezoneFor($user) : '',
            'hour_cycle' => TemporalPreferences::timeFormatFor($user) === '12h' ? 'h12' : 'h23',
            'inline_gregorian' => $user instanceof User && TemporalPreferences::inlineGregorianEquivalentFor($user),
        ];
    }

    /** The data attributes the clock script reads. */
    public static function attributes(?User $user): string
    {
        $settings = self::for($user);

        return sprintf(
            'data-iet-clock data-locale="%s" data-calendar="%s" data-timezone="%s" data-hour-cycle="%s"',
            e($settings['locale']),
            e($settings['calendar']),
            e($settings['timezone']),
            e($settings['hour_cycle']),
        );
    }
}
