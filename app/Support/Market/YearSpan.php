<?php

namespace App\Support\Market;

use Carbon\CarbonImmutable;
use IntlCalendar;

/**
 * A year of manufacture is a span of time, never a single date: Persian 1396 runs from 21 March 2017
 * to 20 March 2018. Storing and comparing the span keeps "model year" unambiguous across calendars
 * and distinct from an actual event date.
 */
final class YearSpan
{
    public const PERSIAN = 'persian';

    public const GREGORIAN = 'gregory';

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} first and last day of the year */
    public static function range(int $year, string $calendar): array
    {
        if ($calendar !== self::PERSIAN) {
            return [CarbonImmutable::create($year, 1, 1, 0, 0, 0, 'UTC'), CarbonImmutable::create($year, 12, 31, 0, 0, 0, 'UTC')];
        }
        $start = self::firstDay($year);

        return [$start, self::firstDay($year + 1)->subDay()];
    }

    /** Legacy numbers without a declared calendar: 1200-1700 reads as Persian, anything later as Gregorian. */
    public static function guessCalendar(int $year): string
    {
        return $year >= 1200 && $year < 1700 ? self::PERSIAN : self::GREGORIAN;
    }

    public static function valid(int $year, string $calendar): bool
    {
        return $calendar === self::PERSIAN ? $year >= 1200 && $year <= 1700 : $year >= 1700 && $year <= 2300;
    }

    /** The other calendar's usual year for the same period, for a hint such as "1396 (about 2017)". */
    public static function approximate(int $year, string $calendar, string $into): int
    {
        if ($calendar === $into) {
            return $year;
        }

        return $calendar === self::PERSIAN ? $year + 621 : $year - 621;
    }

    private static function firstDay(int $persianYear): CarbonImmutable
    {
        $calendar = IntlCalendar::createInstance('UTC', 'en@calendar=persian');
        $calendar->clear();
        $calendar->set(IntlCalendar::FIELD_YEAR, $persianYear);
        $calendar->set(IntlCalendar::FIELD_MONTH, 0);
        $calendar->set(IntlCalendar::FIELD_DAY_OF_MONTH, 1);

        return CarbonImmutable::createFromTimestampUTC((int) ($calendar->getTime() / 1000))->startOfDay();
    }
}
