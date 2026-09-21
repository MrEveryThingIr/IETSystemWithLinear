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
