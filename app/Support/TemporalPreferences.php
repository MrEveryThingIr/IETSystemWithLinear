<?php

namespace App\Support;

use App\CalendarSystem;
use App\Models\User;
use DateTimeZone;
use Throwable;

class TemporalPreferences
{
    public static function calendarFor(?User $user, ?string $locale = null): CalendarSystem
    {
        if ($user?->calendar instanceof CalendarSystem) {
            return $user->calendar;
        }

        return CalendarSystem::tryFrom(Localization::defaultCalendar($locale))
            ?? CalendarSystem::Gregorian;
    }

    public static function timezoneFor(?User $user): string
    {
        if ($user instanceof User && self::validTimezone($user->timezone)) {
            return $user->timezone;
        }

        $fallback = (string) config('app.timezone', 'UTC');

        return self::validTimezone($fallback) ? $fallback : 'UTC';
    }

    public static function dateFormatFor(?User $user): string
    {
        $value = $user?->date_display_format;

        return in_array($value, ['long', 'medium', 'numeric'], true) ? $value : 'long';
    }

    public static function timeFormatFor(?User $user): string
    {
        $value = $user?->time_display_format;

        return in_array($value, ['24h', '12h'], true) ? $value : '24h';
    }

    /** @var list<string> */
    public const GREGORIAN_EQUIVALENT_MODES = ['hidden', 'hover', 'inline'];

    /**
     * How the Gregorian equivalent of a non-Gregorian date is presented.
     * Default is "hover": available on demand, never a permanent second line.
     */
    public static function gregorianEquivalentModeFor(?User $user): string
    {
        if (! $user instanceof User) {
            return 'hover';
        }

        $mode = $user->gregorian_equivalent_mode;
        if (in_array($mode, self::GREGORIAN_EQUIVALENT_MODES, true)) {
            return $mode;
        }

        return $user->show_gregorian_equivalent === false ? 'hidden' : 'hover';
    }

    /** Whether the Gregorian equivalent is available at all (hover or inline). */
    public static function showGregorianEquivalentFor(?User $user): bool
    {
        return self::gregorianEquivalentModeFor($user) !== 'hidden';
    }

    /** Whether the Gregorian equivalent is printed permanently beside the value. */
    public static function inlineGregorianEquivalentFor(?User $user): bool
    {
        return self::gregorianEquivalentModeFor($user) === 'inline';
    }

    /** "Asia/Tehran (UTC+03:30)" — for tooltips and details, not ordinary labels. */
    public static function timezoneDescription(string $timezone, ?\DateTimeInterface $at = null): string
    {
        $offset = \Carbon\CarbonImmutable::instance($at ?? now())->setTimezone($timezone)->format('P');

        return $timezone.' (UTC'.$offset.')';
    }

    public static function validTimezone(?string $timezone): bool
    {
        if ($timezone === null || $timezone === '') {
            return false;
        }

        try {
            new DateTimeZone($timezone);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /** @return list<int> */
    public static function weekdayOrder(?string $locale = null): array
    {
        $first = Localization::firstDayOfWeek($locale);
        $days = [];

        for ($offset = 0; $offset < 7; $offset++) {
            $days[] = (($first - 1 + $offset) % 7) + 1;
        }

        return $days;
    }
}
