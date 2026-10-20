<?php

namespace App\Support\Planner;

use App\Models\PlanOccurrence;
use App\Models\User;
use App\Support\TemporalCalendar;
use App\Support\TemporalPreferences;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;

/**
 * The days the visual time picker offers, grouped the way people think
 * about time: today, tomorrow, the rest of this week, next week, the rest
 * of this month, next month — and, inside a month, its weeks. Weeks start on
 * the viewer's first weekday and months follow their own calendar
 * (Persian months for a Persian-calendar user).
 */
final class PlannerHorizons
{
    public const DAYS_AHEAD = 70;

    /**
     * @return array{
     *     today: string,
     *     days: array<string, array{date: string, weekday: string, short: string, label: string, month: string, today: bool}>,
     *     horizons: array<string, array{days: list<string>, weeks: list<list<string>>}>,
     * }
     */
    public static function for(User $user, ?CarbonImmutable $now = null): array
    {
        $timezone = TemporalPreferences::timezoneFor($user);
        $today = ($now ?? CarbonImmutable::now($timezone))->setTimezone($timezone)->startOfDay();
        $order = TemporalPreferences::weekdayOrder($user->locale);
        $weekStart = $order[0];

        $days = [];
        for ($i = 0; $i < self::DAYS_AHEAD; $i++) {
            $date = $today->addDays($i);
            $days[$date->toDateString()] = [
                'date' => $date->toDateString(),
                'weekday' => TemporalCalendar::format($date, $user, $timezone, 'EEEE'),
                'short' => TemporalCalendar::format($date, $user, $timezone, 'EEE'),
                'label' => TemporalCalendar::format($date, $user, $timezone, 'd MMM'),
                'month' => TemporalCalendar::monthLabel($date, $user, $timezone),
                'today' => $i === 0,
            ];
        }

        $daysUntilWeekEnd = (($weekStart - $today->isoWeekday() + 7) % 7) ?: 7;
        $thisWeekEnd = $today->addDays($daysUntilWeekEnd);          // exclusive: next week's first day
        $nextWeekEnd = $thisWeekEnd->addDays(7);
        $thisMonthStart = TemporalCalendar::monthStart($today, $user, $timezone);
        $nextMonthStart = TemporalCalendar::nextMonthStart($thisMonthStart, $user, $timezone);
        $monthAfter = TemporalCalendar::nextMonthStart($nextMonthStart, $user, $timezone);

        $range = fn (CarbonImmutable $from, CarbonImmutable $until): array => array_values(array_filter(
            array_keys($days),
            fn (string $date): bool => $date >= $from->toDateString() && $date < $until->toDateString(),
        ));

        $horizons = [
            'today' => $range($today, $today->addDay()),
            'tomorrow' => $range($today->addDay(), $today->addDays(2)),
            'this_week' => $range($today, $thisWeekEnd),
            'next_week' => $range($thisWeekEnd, $nextWeekEnd),
            'this_month' => $range($today, $nextMonthStart),
            'next_month' => $range($nextMonthStart, $monthAfter),
        ];

        return [
            'today' => $today->toDateString(),
            'days' => $days,
            'horizons' => array_map(fn (array $dates): array => [
                'days' => $dates,
                'weeks' => self::weeks($dates, $weekStart),
            ], $horizons),
        ];
    }

    /**
     * Busy time per day for the picker: [startMinute, endMinute, title, colour].
     *
     * @return array<string, list<array{0: int, 1: int, 2: string, 3: string}>>
     */
    public static function busy(User $user, int $daysAhead = self::DAYS_AHEAD): array
    {
        $timezone = TemporalPreferences::timezoneFor($user);
        $from = CarbonImmutable::now($timezone)->startOfDay();
        $until = $from->addDays($daysAhead);
        $appearance = PlanAppearance::forActor($user->actor?->id);

        $busy = [];
        PlanOccurrence::query()
            ->with('plan')
            ->where('scheduled_start_at', '<', $until->utc())
            ->where('scheduled_end_at', '>', $from->utc())
            ->whereIn('status', ['scheduled', 'in_progress'])
            ->orderBy('scheduled_start_at')
            ->limit(400)
            ->get()
            ->filter(fn (PlanOccurrence $occurrence): bool => Gate::forUser($user)->allows('view', $occurrence->plan))
            ->each(function (PlanOccurrence $occurrence) use (&$busy, $timezone, $appearance): void {
                $start = $occurrence->scheduled_start_at->setTimezone($timezone);
                $end = $occurrence->scheduled_end_at->setTimezone($timezone);
                $day = $start->startOfDay();
                while ($day->lt($end)) {
                    $dayEnd = $day->addDay();
                    $from = $start->gt($day) ? (int) $day->diffInMinutes($start) : 0;
                    $to = $end->lt($dayEnd) ? (int) $day->diffInMinutes($end) : 1440;
                    if ($to > $from) {
                        $busy[$day->toDateString()][] = [$from, $to, (string) $occurrence->plan->title, $appearance->of($occurrence->plan)['color']];
                    }
                    $day = $dayEnd;
                }
            });

        return $busy;
    }

    /**
     * @param  list<string>  $dates
     * @return list<list<string>>
     */
    private static function weeks(array $dates, int $weekStart): array
    {
        $weeks = [];
        $current = [];
        foreach ($dates as $date) {
            if ($current !== [] && CarbonImmutable::parse($date)->isoWeekday() === $weekStart) {
                $weeks[] = $current;
                $current = [];
            }
            $current[] = $date;
        }
        if ($current !== []) {
            $weeks[] = $current;
        }

        return $weeks;
    }
}
