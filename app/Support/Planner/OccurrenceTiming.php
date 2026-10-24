<?php

namespace App\Support\Planner;

use App\Models\PlanOccurrence;
use App\PlanOccurrenceStatus;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The one place that knows what an occurrence means at a given moment and
 * what may be done with it. Every screen, button, reminder and action asks
 * here, so nothing can be "done" before its time and nothing waits forever.
 *
 * Stored facts: the expected time and allowed window (fixed when the
 * occurrence is created), when it closes, and what the person did or told.
 * The phase is derived from those facts and the clock; no background job is
 * needed for an occurrence to move on.
 *
 * How long an occurrence stays possible depends on what it is:
 *  - window (appointments, meetings, events): only inside the allowed window;
 *  - day (routines, tasks, focus time): late is still possible the same day;
 *  - open (due dates, reminders): stays open until handled, and may be
 *    handled early (only the nearest one of a series).
 * After it closes, the person can still tell what happened for REPORT_DAYS.
 * A missing report is never treated as "not done".
 */
final class OccurrenceTiming
{
    public const REPORT_DAYS = 7;

    public const WINDOW = 'window';

    public const DAY = 'day';

    public const OPEN = 'open';

    public const POLICIES = [self::WINDOW, self::DAY, self::OPEN];

    /** Phases that still expect something from the person. */
    public const PENDING = ['upcoming', 'now', 'late', 'overdue', 'in_progress', 'awaiting_report'];

    /** Phases that are finished, with an outcome told by a person. */
    public const OUTCOMES = ['done', 'partly', 'skipped', 'not_done'];

    public static function policyForKind(?string $kind): string
    {
        return match ($kind) {
            'event', 'meeting' => self::WINDOW,
            'due', 'reminder' => self::OPEN,
            default => self::DAY,
        };
    }

    /** When the occurrence stops being possible to do (null: never, it stays open). */
    public static function closesAt(string $policy, CarbonInterface $scheduledStart, CarbonInterface $windowEnd, string $timezone): ?CarbonImmutable
    {
        $windowEnd = CarbonImmutable::instance($windowEnd)->utc();

        return match ($policy) {
            self::OPEN => null,
            self::WINDOW => $windowEnd,
            default => (function () use ($scheduledStart, $windowEnd, $timezone): CarbonImmutable {
                $endOfDay = CarbonImmutable::instance($scheduledStart)->setTimezone($timezone)->endOfDay()->startOfSecond()->utc();

                return $endOfDay->greaterThan($windowEnd) ? $endOfDay : $windowEnd;
            })(),
        };
    }

    public static function policyOf(PlanOccurrence $occurrence): string
    {
        $policy = $occurrence->scheduleRule?->close_policy;
        if (in_array($policy, self::POLICIES, true)) {
            return $policy;
        }

        return self::policyForKind(is_array($occurrence->plan?->metadata) ? ($occurrence->plan->metadata['kind'] ?? null) : null);
    }

    public static function phase(PlanOccurrence $occurrence, ?CarbonInterface $at = null): string
    {
        $now = self::moment($at);

        switch ($occurrence->status) {
            case PlanOccurrenceStatus::Cancelled:
                return 'cancelled';
            case PlanOccurrenceStatus::Completed:
                return $occurrence->completion_percent !== null && $occurrence->completion_percent < 100 ? 'partly' : 'done';
            case PlanOccurrenceStatus::Skipped:
                // Saying "not this time" before it started is keeping your word, not failing it.
                return $occurrence->reported_at !== null && $occurrence->reported_at->lessThan($occurrence->window_start_at) ? 'skipped' : 'not_done';
            case PlanOccurrenceStatus::InProgress:
                return $occurrence->closes_at !== null && $now->greaterThan($occurrence->closes_at) ? 'awaiting_report' : 'in_progress';
        }

        if ($now->lessThan($occurrence->window_start_at)) {
            return 'upcoming';
        }
        if ($now->lessThanOrEqualTo($occurrence->window_end_at)) {
            return 'now';
        }
        if ($occurrence->closes_at === null) {
            return 'overdue';
        }
        if ($now->lessThanOrEqualTo($occurrence->closes_at)) {
            return 'late';
        }

        return $now->lessThanOrEqualTo($occurrence->closes_at->addDays(self::REPORT_DAYS)) ? 'awaiting_report' : 'no_report';
    }

    /**
     * What a participant may do now. "Done" means it was done now; "report"
     * tells what happened with times (also afterwards); "correct" changes an
     * earlier answer while the report period lasts.
     *
     * @return list<string>
     */
    public static function actions(PlanOccurrence $occurrence, ?CarbonInterface $at = null, ?bool $nearestPending = null): array
    {
        $now = self::moment($at);
        $phase = self::phase($occurrence, $now);

        return match ($phase) {
            'upcoming' => self::policyOf($occurrence) === self::OPEN && ($nearestPending ?? self::isNearestPending($occurrence))
                ? ['done', 'report', 'skip', 'cancel']
                : ['skip', 'cancel'],
            'now', 'late', 'overdue' => ['start', 'done', 'report', 'skip', 'cancel'],
            'in_progress' => ['done', 'report', 'cancel'],
            'awaiting_report' => ['report'],
            'done', 'partly', 'skipped', 'not_done' => $now->lessThanOrEqualTo(self::correctableUntil($occurrence)) ? ['correct'] : [],
            default => [],
        };
    }

    public static function allows(PlanOccurrence $occurrence, string $action, ?CarbonInterface $at = null): bool
    {
        return in_array($action, self::actions($occurrence, $at), true);
    }

    /** The last moment an occurrence without an answer can still be told about. */
    public static function reportUntil(PlanOccurrence $occurrence): ?CarbonImmutable
    {
        return $occurrence->closes_at?->addDays(self::REPORT_DAYS);
    }

    /** The last moment an answer can still be changed. */
    public static function correctableUntil(PlanOccurrence $occurrence): CarbonImmutable
    {
        $base = $occurrence->closes_at ?? $occurrence->reported_at ?? $occurrence->completed_at ?? $occurrence->window_end_at;
        if ($occurrence->reported_at !== null && $occurrence->reported_at->greaterThan($base)) {
            $base = $occurrence->reported_at;
        }

        return CarbonImmutable::instance($base)->addDays(self::REPORT_DAYS);
    }

    /**
     * Minutes between the expected start and the actual one: positive is
     * later than expected, negative earlier. Null when nothing was done.
     */
    public static function deviationMinutes(PlanOccurrence $occurrence, ?CarbonInterface $expected = null): ?int
    {
        if ($occurrence->actual_start_at === null || ! in_array($occurrence->status, [PlanOccurrenceStatus::Completed, PlanOccurrenceStatus::InProgress], true)) {
            return null;
        }
        $expected ??= $occurrence->scheduled_start_at;

        return (int) round(($occurrence->actual_start_at->getTimestamp() - $expected->getTimestamp()) / 60);
    }

    /** Within what the plan allows (its early and late margins). */
    public static function withinWindow(PlanOccurrence $occurrence): ?bool
    {
        if ($occurrence->actual_start_at === null || $occurrence->status !== PlanOccurrenceStatus::Completed) {
            return null;
        }

        return $occurrence->actual_start_at->betweenIncluded($occurrence->window_start_at->subMinute(), $occurrence->window_end_at->addMinute());
    }

    private static function isNearestPending(PlanOccurrence $occurrence): bool
    {
        $nearest = PlanOccurrence::query()
            ->where('plan_id', $occurrence->plan_id)
            ->where('schedule_rule_id', $occurrence->schedule_rule_id)
            ->whereIn('status', [PlanOccurrenceStatus::Scheduled->value, PlanOccurrenceStatus::InProgress->value])
            ->orderBy('scheduled_start_at')
            ->orderBy('id')
            ->value('id');

        return (int) $nearest === (int) $occurrence->id;
    }

    private static function moment(?CarbonInterface $at): CarbonImmutable
    {
        return $at === null ? CarbonImmutable::now('UTC') : CarbonImmutable::instance($at)->utc();
    }
}
