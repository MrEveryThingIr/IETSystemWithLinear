<?php

namespace App\Support;

use App\CalendarSystem;
use App\Models\User;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use IntlDateFormatter;
use LogicException;
use Throwable;

class TemporalCalendar
{
    public static function monthStart(CarbonImmutable $date, ?User $user, string $timezone): CarbonImmutable
    {
        $local = $date->setTimezone($timezone)->startOfDay();
        $day = (int) self::format($local, $user, $timezone, 'd', 'en');

        return $day >= 1 && $day <= 31 ? $local->subDays($day - 1) : $local->startOfMonth();
    }

    public static function nextMonthStart(CarbonImmutable $monthStart, ?User $user, string $timezone): CarbonImmutable
    {
        $first = self::monthStart($monthStart, $user, $timezone);
        $key = self::monthKey($first, $user, $timezone);

        for ($offset = 25; $offset <= 40; $offset++) {
            $candidate = $first->addDays($offset);
            if ((int) self::format($candidate, $user, $timezone, 'd', 'en') === 1
                && self::monthKey($candidate, $user, $timezone) !== $key) {
                return $candidate->startOfDay();
            }
        }

        return $first->addMonth()->startOfMonth();
    }

    public static function previousMonthStart(CarbonImmutable $monthStart, ?User $user, string $timezone): CarbonImmutable
    {
        return self::monthStart(self::monthStart($monthStart, $user, $timezone)->subDay(), $user, $timezone);
    }

    public static function yearStart(CarbonImmutable $date, ?User $user, string $timezone): CarbonImmutable
    {
        $month = self::monthStart($date, $user, $timezone);

        for ($guard = 0; $guard < 12; $guard++) {
            if ((int) self::format($month, $user, $timezone, 'M', 'en') === 1) {
                return $month;
            }

            $month = self::previousMonthStart($month, $user, $timezone);
        }

        return $date->setTimezone($timezone)->startOfYear();
    }

    public static function nextYearStart(CarbonImmutable $yearStart, ?User $user, string $timezone): CarbonImmutable
    {
        $month = self::yearStart($yearStart, $user, $timezone);

        for ($index = 0; $index < 12; $index++) {
            $month = self::nextMonthStart($month, $user, $timezone);
        }

        return $month;
    }

    public static function previousYearStart(CarbonImmutable $yearStart, ?User $user, string $timezone): CarbonImmutable
    {
        return self::yearStart(self::yearStart($yearStart, $user, $timezone)->subDay(), $user, $timezone);
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

    public static function dateLabel(
        DateTimeInterface $date,
        ?User $user,
        ?string $timezone = null,
        ?CalendarSystem $calendar = null,
    ): string {
        $timezone ??= TemporalPreferences::timezoneFor($user);

        return self::format($date, $user, $timezone, 'd MMMM y', calendar: $calendar);
    }

    public static function dateTimeLabel(
        DateTimeInterface $date,
        ?User $user,
        ?string $timezone = null,
        bool $seconds = false,
        ?CalendarSystem $calendar = null,
    ): string {
        $timezone ??= TemporalPreferences::timezoneFor($user);
        $pattern = $seconds ? 'd MMMM y, HH:mm:ss z' : 'd MMMM y, HH:mm z';

        return self::format($date, $user, $timezone, $pattern, calendar: $calendar);
    }

    public static function timeLabel(
        DateTimeInterface $date,
        ?User $user,
        ?string $timezone = null,
    ): string {
        $timezone ??= TemporalPreferences::timezoneFor($user);

        return self::format($date, $user, $timezone, 'HH:mm');
    }

    public static function format(
        DateTimeInterface $date,
        ?User $user,
        string $timezone,
        string $pattern,
        ?string $locale = null,
        ?CalendarSystem $calendar = null,
    ): string {
        if (! class_exists(IntlDateFormatter::class)) {
            throw new LogicException('The PHP intl extension is required for profile-aware calendar rendering.');
        }

        $calendar ??= TemporalPreferences::calendarFor($user);
        $locale ??= Localization::intlLocale($user?->locale);
        $icuCalendar = match ($calendar) {
            CalendarSystem::Gregorian => 'gregorian',
            CalendarSystem::Persian => 'persian',
            CalendarSystem::IslamicUmmAlQura => 'islamic-umalqura',
        };
        $icuLocale = str_replace('-', '_', $locale).'@calendar='.$icuCalendar;

        try {
            $formatter = new IntlDateFormatter(
                $icuLocale,
                IntlDateFormatter::NONE,
                IntlDateFormatter::NONE,
                $timezone,
                IntlDateFormatter::TRADITIONAL,
                $pattern,
            );

            $formatted = $formatter->format($date);
            if (is_string($formatted) && $formatted !== '') {
                return $formatted;
            }

            if ($calendar !== CalendarSystem::Gregorian) {
                throw new LogicException('ICU could not format the selected non-Gregorian calendar.');
            }
        } catch (Throwable $exception) {
            if ($calendar !== CalendarSystem::Gregorian) {
                throw new LogicException(
                    'The selected profile calendar could not be rendered by PHP intl.',
                    previous: $exception,
                );
            }
        }

        return CarbonImmutable::instance($date)->setTimezone($timezone)->format(match ($pattern) {
            'y' => 'Y',
            'M' => 'n',
            'MM' => 'm',
            'MMMM' => 'F',
            'd' => 'j',
            default => 'Y-m-d',
        });
    }
}
