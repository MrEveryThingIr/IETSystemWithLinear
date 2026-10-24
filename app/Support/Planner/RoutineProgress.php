<?php

namespace App\Support\Planner;

use App\Models\Plan;
use App\Models\Planner\PlanPeriod;
use App\Models\Planner\PlanUrge;
use App\Models\PlanOccurrence;
use App\PlanOccurrenceStatus;
use Carbon\CarbonImmutable;

/**
 * Expected versus actual, day by day, for a repeating plan; and the same
 * figures per tuning period so periods can be compared. Only facts the
 * person recorded are counted: a day without a report is "no report", not
 * a failure, and the summary says how many days it rests on.
 */
final class RoutineProgress
{
    /** Minutes of change between the first and second half that count as a trend. */
    private const TREND_MINUTES = 3;

    /**
     * @return array{rows: list<array{occurrence: PlanOccurrence, day: int, date: string, expected: CarbonImmutable, actual: ?CarbonImmutable, deviation: ?int, from_ideal: ?int, on_target: ?bool, phase: string}>, summary: array<string, mixed>}
     */
    public static function between(Plan $plan, string $from, string $to, ?PlanPeriod $period = null, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now('UTC');
        $occurrences = PlanOccurrence::query()
            ->with(['scheduleRule', 'plan'])
            ->where('plan_id', $plan->id)
            ->whereDate('local_date', '>=', $from)
            ->whereDate('local_date', '<=', $to)
            ->where('status', '!=', PlanOccurrenceStatus::Cancelled->value)
            ->orderBy('scheduled_start_at')
            ->limit(400)
            ->get();

        $start = CarbonImmutable::parse($from);
        $rows = $occurrences->map(function (PlanOccurrence $occurrence) use ($period, $now, $start): array {
            $expected = self::expected($occurrence, $period);
            $phase = OccurrenceTiming::phase($occurrence, $now);
            $deviation = OccurrenceTiming::deviationMinutes($occurrence, $expected);
            $before = (int) ($occurrence->scheduleRule->window_before_minutes ?? 0);
            $after = (int) ($occurrence->scheduleRule->window_after_minutes ?? 0);

            return [
                'occurrence' => $occurrence,
                'day' => (int) $start->diffInDays(CarbonImmutable::parse($occurrence->local_date->toDateString())) + 1,
                'date' => $occurrence->local_date->toDateString(),
                'expected' => $expected,
                'actual' => in_array($phase, ['done', 'partly', 'in_progress'], true) ? $occurrence->actual_start_at : null,
                'deviation' => $deviation,
                'from_ideal' => OccurrenceTiming::deviationMinutes($occurrence),
                // On target: not later than the expected time allows, and not earlier than the ideal allows
                // (with an in-between target, anything from the ideal to that target counts).
                'on_target' => $deviation === null || ! in_array($phase, ['done', 'partly'], true)
                    ? null
                    : ($deviation <= $after && $occurrence->actual_start_at->greaterThanOrEqualTo(($expected->lessThan($occurrence->scheduled_start_at) ? $expected : $occurrence->scheduled_start_at)->subMinutes($before))),
                'phase' => $phase,
            ];
        })->values();

        $list = $rows->all();

        return ['rows' => $list, 'summary' => self::summarize($list)];
    }

    /**
     * Each period of a plan with its own summary (and urge figures for a
     * habit being replaced).
     *
     * @return list<array{period: PlanPeriod, summary: array<string, mixed>, urges: ?array<string, mixed>}>
     */
    public static function periods(Plan $plan, bool $replaces, ?CarbonImmutable $now = null): array
    {
        return $plan->periods->map(function (PlanPeriod $period) use ($plan, $replaces, $now): array {
            $to = $period->lastDay()->toDateString();

            return [
                'period' => $period,
                'summary' => self::between($plan, $period->starts_on->toDateString(), $to, $period, $now)['summary'],
                'urges' => $replaces ? self::urges($plan, $period->starts_on->toDateString(), $to, $period) : null,
            ];
        })->values()->all();
    }

    /**
     * Urges in a date range (local dates of the person): how often, how many
     * were replaced, how strong, per day.
     *
     * @return array{total: int, replaced: int, gave_in: int, rate: ?int, per_day: ?float, strength: ?float, days: array<string, array{replaced: int, gave_in: int}>}
     */
    public static function urges(Plan $plan, string $from, string $to, ?PlanPeriod $period = null): array
    {
        $timezone = $plan->timezone ?: 'UTC';
        $urges = PlanUrge::query()
            ->where('plan_id', $plan->id)
            ->whereBetween('occurred_at', [
                CarbonImmutable::parse($from, $timezone)->startOfDay()->utc(),
                CarbonImmutable::parse($to, $timezone)->endOfDay()->utc(),
            ])
            ->orderBy('occurred_at')
            ->get(['outcome', 'strength', 'occurred_at']);

        $days = [];
        foreach ($urges as $urge) {
            $key = $urge->occurred_at->setTimezone($timezone)->toDateString();
            $days[$key] ??= ['replaced' => 0, 'gave_in' => 0];
            $days[$key][$urge->outcome === 'replaced' ? 'replaced' : 'gave_in']++;
        }
        $total = $urges->count();
        $replaced = $urges->where('outcome', 'replaced')->count();
        $length = max(1, (int) CarbonImmutable::parse($from)->diffInDays(CarbonImmutable::parse(min($to, CarbonImmutable::now($timezone)->toDateString()))) + 1);
        $strengths = $urges->pluck('strength')->filter();

        return [
            'total' => $total,
            'replaced' => $replaced,
            'gave_in' => $total - $replaced,
            'rate' => $total > 0 ? (int) round($replaced * 100 / $total) : null,
            'per_day' => $total > 0 ? round($total / $length, 1) : null,
            'strength' => $strengths->isNotEmpty() ? round((float) $strengths->avg(), 1) : null,
            'days' => $days,
        ];
    }

    /** Expected start: the period's in-between target on that day, otherwise the plan's own time. */
    public static function expected(PlanOccurrence $occurrence, ?PlanPeriod $period): CarbonImmutable
    {
        if ($period?->target_time !== null && $period->covers($occurrence->local_date->toDateString())) {
            return CarbonImmutable::parse($occurrence->local_date->toDateString().' '.$period->target_time, $occurrence->timezone)->utc();
        }

        return $occurrence->scheduled_start_at;
    }

    /**
     * @param  list<array{occurrence: PlanOccurrence, day: int, date: string, expected: CarbonImmutable, actual: ?CarbonImmutable, deviation: ?int, from_ideal: ?int, on_target: ?bool, phase: string}>  $list
     * @return array<string, mixed>
     */
    private static function summarize(array $list): array
    {
        $rows = collect($list);
        $count = fn (array $phases): int => $rows->whereIn('phase', $phases)->count();
        $doneRows = $rows->whereIn('phase', ['done', 'partly'])->filter(fn (array $row): bool => $row['deviation'] !== null)->values();
        $past = $rows->reject(fn (array $row): bool => in_array($row['phase'], ['upcoming', 'now', 'late', 'overdue', 'in_progress'], true));
        $reasons = $rows->map(fn (array $row) => $row['occurrence']->reason)->filter()->countBy()->sortDesc();
        $difficulty = $rows->map(fn (array $row) => $row['occurrence']->difficulty)->filter();

        $trend = null;
        $firstAverage = $lastAverage = null;
        if ($doneRows->count() >= 4) {
            $half = intdiv($doneRows->count(), 2);
            $firstAverage = (int) round($doneRows->take($half)->avg(fn ($row) => abs((int) $row['from_ideal'])));
            $lastAverage = (int) round($doneRows->slice($doneRows->count() - $half)->avg(fn ($row) => abs((int) $row['from_ideal'])));
            $trend = $lastAverage < $firstAverage - self::TREND_MINUTES ? 'closer' : ($lastAverage > $firstAverage + self::TREND_MINUTES ? 'further' : 'steady');
        }

        $reported = $past->whereIn('phase', ['done', 'partly', 'skipped', 'not_done'])->count();

        return [
            'days' => $rows->count(),
            'past' => $past->count(),
            'done' => $count(['done']),
            'partly' => $count(['partly']),
            'not_done' => $count(['not_done']),
            'skipped' => $count(['skipped']),
            'no_report' => $count(['awaiting_report', 'no_report']),
            'on_target' => $rows->where('on_target', true)->count(),
            'average_deviation' => $doneRows->isNotEmpty() ? (int) round($doneRows->avg('deviation')) : null,
            'average_from_ideal' => $doneRows->isNotEmpty() ? (int) round($doneRows->avg('from_ideal')) : null,
            'done_rate' => $past->count() > 0 ? (int) round(($count(['done', 'partly'])) * 100 / $past->count()) : null,
            'on_target_rate' => $past->count() > 0 ? (int) round($rows->where('on_target', true)->count() * 100 / $past->count()) : null,
            'reported_rate' => $past->count() > 0 ? (int) round($reported * 100 / $past->count()) : null,
            'trend' => $trend,
            'first_average' => $firstAverage,
            'last_average' => $lastAverage,
            'top_reason' => $reasons->keys()->first(),
            'top_reason_count' => (int) ($reasons->first() ?? 0),
            'difficulty' => $difficulty->isNotEmpty() ? round((float) $difficulty->avg(), 1) : null,
        ];
    }
}
