<?php

namespace App\Support;

use App\CalendarSystem;
use App\Models\User;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use IntlDateFormatter;
use LogicException;

/**
 * Presentation-only calendar arithmetic. All returned dates are local Gregorian
 * instants; no schedule or occurrence is rewritten when preferences change.
 */
class TemporalCalendar
{
    public static function monthStart(CarbonImmutable $date, ?User $user, string $timezone): CarbonImmutable
    {
        $local = $date->setTimezone($timezone)->startOfDay();
        $day = (int) self::format($local, $user, $timezone, 'd', 'en');

        return $local->subDays($day - 1);
    }

    public static function nextMonthStart(CarbonImmutable $date, ?User $user, string $timezone): CarbonImmutable
    {
        $first = self::monthStart($date, $user, $timezone);
        $key = self::monthKey($first, $user, $timezone);

        for ($offset = 25; $offset <= 40; $offset++) {
            $candidate = $first->addDays($offset);
            if ((int) self::format($candidate, $user, $timezone, 'd', 'en') === 1
                && self::monthKey($candidate, $user, $timezone) !== $key) {
                return $candidate;
            }
        }

        throw new LogicException('Unable to resolve the next calendar month.');
    }

    public static function previousMonthStart(CarbonImmutable $date, ?User $user, string $timezone): CarbonImmutable
    {
        return self::monthStart(self::monthStart($date, $user, $timezone)->subDay(), $user, $timezone);
    }

    public static function yearStart(CarbonImmutable $date, ?User $user, string $timezone): CarbonImmutable
    {
        $month = self::monthStart($date, $user, $timezone);

        for ($index = 0; $index < 12; $index++) {
            if ((int) self::format($month, $user, $timezone, 'M', 'en') === 1) {
                return $month;
            }

            $month = self::previousMonthStart($month, $user, $timezone);
        }

        throw new LogicException('Unable to resolve the calendar year.');
    }

    public static function nextYearStart(CarbonImmutable $date, ?User $user, string $timezone): CarbonImmutable
    {
        $month = self::yearStart($date, $user, $timezone);

        for ($index = 0; $index < 12; $index++) {
            $month = self::nextMonthStart($month, $user, $timezone);
        }

        return $month;
    }

    public static function previousYearStart(CarbonImmutable $date, ?User $user, string $timezone): CarbonImmutable
    {
        return self::yearStart(self::yearStart($date, $user, $timezone)->subDay(), $user, $timezone);
    }

    public static function monthKey(DateTimeInterface $date, ?User $user, string $timezone): string
    {
        return self::format($date, $user, $timezone, 'y-MM', 'en');
    }

    public static function yearLabel(DateTimeInterface $date, ?User $user, string $timezone): string
    {
        return self::format($date, $user, $timezone, 'y');
    }

    public static function monthLabel(DateTimeInterface $date, ?User $user, string $timezone): string
    {
        return self::format($date, $user, $timezone, 'MMMM');
    }

    public static function dayLabel(DateTimeInterface $date, ?User $user, string $timezone): string
    {
        return self::format($date, $user, $timezone, 'd');
    }

    public static function dateLabel(DateTimeInterface $date, ?User $user, string $timezone): string
    {
        return self::format($date, $user, $timezone, 'd MMMM y');
    }

    public static function timeLabel(DateTimeInterface $date, ?User $user, string $timezone): string
    {
        return self::format($date, $user, $timezone, 'HH:mm');
    }

    public static function format(DateTimeInterface $date, ?User $user, string $timezone, string $pattern, ?string $locale = null): string
    {
        $calendar = TemporalPreferences::calendarFor($user);
        $icuCalendar = match ($calendar) {
            CalendarSystem::Gregorian => 'gregorian',
            CalendarSystem::Persian => 'persian',
            CalendarSystem::IslamicUmmAlQura => 'islamic-umalqura',
        };
        $locale = str_replace('-', '_', $locale ?? Localization::intlLocale($user?->locale));
        $formatter = new IntlDateFormatter(
            $locale.'@calendar='.$icuCalendar,
            IntlDateFormatter::NONE,
            IntlDateFormatter::NONE,
            $timezone,
            IntlDateFormatter::TRADITIONAL,
            $pattern,
        );
        $formatted = $formatter->format($date);

        if (! is_string($formatted) || $formatted === '') {
            throw new LogicException('The selected profile calendar could not be rendered by PHP intl.');
        }

        return $formatted;
    }
}
