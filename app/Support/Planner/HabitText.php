<?php

namespace App\Support\Planner;

use App\Models\PlanOccurrence;
use App\Models\User;
use App\Support\Localization;
use App\Support\TemporalCalendar;
use DateTimeInterface;
use NumberFormatter;

/** Short, translated wording for minutes and differences ("+48 min", "1 h 12 min early"). */
final class HabitText
{
    public static function number(int|float $value): string
    {
        static $formatters = [];
        $locale = Localization::intlLocale();
        $formatters[$locale] ??= class_exists(NumberFormatter::class) ? new NumberFormatter($locale, NumberFormatter::DECIMAL) : null;
        $formatted = $formatters[$locale]?->format($value);

        return is_string($formatted) && $formatted !== '' ? $formatted : (string) $value;
    }

    public static function minutes(?int $minutes): string
    {
        if ($minutes === null) {
            return '—';
        }
        $minutes = abs($minutes);
        if ($minutes < 60) {
            return (string) __('habits.time.minutes', ['count' => self::number($minutes)]);
        }
        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        return $rest === 0
            ? (string) __('habits.time.hours', ['count' => self::number($hours)])
            : (string) __('habits.time.hours_minutes', ['hours' => self::number($hours), 'minutes' => self::number($rest)]);
    }

    /** Positive: later than expected; negative: earlier. */
    public static function deviation(?int $minutes): string
    {
        if ($minutes === null) {
            return '—';
        }
        if ($minutes === 0) {
            return (string) __('habits.time.exact');
        }

        return (string) __($minutes > 0 ? 'habits.time.later' : 'habits.time.earlier', ['time' => self::minutes($minutes)]);
    }

    /** One plain sentence about what this occurrence means now. */
    public static function phaseHelp(PlanOccurrence $occurrence, ?string $phase = null): string
    {
        $phase ??= OccurrenceTiming::phase($occurrence);
        $user = request()->user();
        $time = fn (?DateTimeInterface $value): string => $value === null ? '' : TemporalCalendar::timeLabel($value, $user instanceof User ? $user : null, $occurrence->timezone);
        $moment = fn (?DateTimeInterface $value): string => $value === null ? '' : TemporalCalendar::dateTimeLabel($value, $user instanceof User ? $user : null, $occurrence->timezone);
        $open = OccurrenceTiming::policyOf($occurrence) === OccurrenceTiming::OPEN;

        return (string) match ($phase) {
            'upcoming' => $open
                ? __('habits.phase_help.upcoming_open', ['time' => $moment($occurrence->scheduled_start_at)])
                : __('habits.phase_help.upcoming', ['time' => $moment($occurrence->window_start_at)]),
            'now' => __('habits.phase_help.now', ['expected' => $time($occurrence->scheduled_start_at), 'until' => $time($occurrence->window_end_at)]),
            'late' => __('habits.phase_help.late', ['until' => $time($occurrence->closes_at)]),
            'overdue' => __('habits.phase_help.overdue', ['time' => $moment($occurrence->scheduled_start_at)]),
            'in_progress' => __('habits.phase_help.in_progress', ['time' => $time($occurrence->actual_start_at)]),
            'awaiting_report' => __('habits.phase_help.awaiting_report', ['until' => $moment(OccurrenceTiming::reportUntil($occurrence))]),
            'no_report' => __('habits.phase_help.no_report'),
            'done' => __('habits.phase_help.done', ['time' => $time($occurrence->actual_start_at), 'diff' => self::deviation(OccurrenceTiming::deviationMinutes($occurrence))]),
            'partly' => __('habits.phase_help.partly', ['percent' => self::number((int) $occurrence->completion_percent), 'time' => $time($occurrence->actual_start_at)]),
            'skipped' => __('habits.phase_help.skipped'),
            'not_done' => __('habits.phase_help.not_done'),
            default => __('habits.phase_help.cancelled'),
        };
    }
}
