<?php

namespace App\Support\Planner;

use App\CalendarSystem;
use DateTimeInterface;
use IntlCalendar;
use IntlTimeZone;

/**
 * Where a date falls in a calendar's months, so "every month on the 5th"
 * means the 5th of each Persian month for a Persian-calendar user. A day
 * the month does not have (31 in a 30-day month) falls on its last day.
 */
final class CalendarMonths
{
    /** @return array{index: int, day: int, length: int} months since year 0, day of month, days in month */
    public static function position(DateTimeInterface $date, CalendarSystem $calendar, string $timezone): array
    {
        $icu = match ($calendar) {
            CalendarSystem::Persian => 'persian',
            CalendarSystem::IslamicUmmAlQura => 'islamic-umalqura',
            CalendarSystem::Gregorian => 'gregorian',
        };
        // Read at noon so daylight-saving edges cannot move the date.
        $instance = self::fromGregorian($date, $icu, $timezone);

        return [
            'index' => $instance->get(IntlCalendar::FIELD_YEAR) * 13 + $instance->get(IntlCalendar::FIELD_MONTH),
            'day' => $instance->get(IntlCalendar::FIELD_DAY_OF_MONTH),
            'length' => $instance->getActualMaximum(IntlCalendar::FIELD_DAY_OF_MONTH),
        ];
    }

    /** Whether $date is the monthly day ($monthDay, clamped to the month's length). */
    public static function isMonthlyDay(DateTimeInterface $date, DateTimeInterface $start, int $monthDay, int $interval, CalendarSystem $calendar, string $timezone): bool
    {
        $here = self::position($date, $calendar, $timezone);
        if ($here['day'] !== min($monthDay, $here['length'])) {
            return false;
        }
        $first = self::position($start, $calendar, $timezone);
        $months = self::monthsBetween($first['index'], $here['index']);

        return $months >= 0 && $months % max(1, $interval) === 0;
    }

    public static function dayOf(DateTimeInterface $date, CalendarSystem $calendar, string $timezone): int
    {
        return self::position($date, $calendar, $timezone)['day'];
    }

    private static function monthsBetween(int $from, int $to): int
    {
        // index = year * 13 + month: convert back to a running month count.
        $count = fn (int $index): int => intdiv($index, 13) * 12 + ($index % 13);

        return $count($to) - $count($from);
    }

    private static function fromGregorian(DateTimeInterface $date, string $icu, string $timezone): IntlCalendar
    {
        $gregorian = IntlCalendar::createInstance(IntlTimeZone::createTimeZone($timezone), 'en_US@calendar=gregorian');
        $gregorian->set((int) $date->format('Y'), (int) $date->format('n') - 1, (int) $date->format('j'), 12, 0, 0);
        $target = IntlCalendar::createInstance(IntlTimeZone::createTimeZone($timezone), 'en_US@calendar='.$icu);
        $target->setTime($gregorian->getTime());

        return $target;
    }
}
