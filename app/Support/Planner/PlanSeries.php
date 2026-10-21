<?php

namespace App\Support\Planner;

use App\Models\Plan;
use App\Models\PlanScheduleRule;
use App\PlanScheduleFrequency;
use App\PlanScheduleRuleStatus;

/**
 * A plan's repetition in one short phrase ("Every week on Mon, Wed"), so a
 * repeating plan is shown once, not as a long list of its occurrences.
 */
final class PlanSeries
{
    public static function summary(Plan $plan): string
    {
        $rule = $plan->relationLoaded('scheduleRules')
            ? $plan->scheduleRules->first(fn (PlanScheduleRule $rule): bool => $rule->status === PlanScheduleRuleStatus::Active)
            : $plan->scheduleRules()->where('status', PlanScheduleRuleStatus::Active->value)->latest('id')->first();

        if (! $rule instanceof PlanScheduleRule) {
            return '';
        }

        $interval = max(1, (int) $rule->interval);

        return match ($rule->frequency) {
            PlanScheduleFrequency::Once => (string) __('planner.series.once'),
            PlanScheduleFrequency::Daily => trans_choice('planner.series.daily', $interval, ['count' => $interval]),
            PlanScheduleFrequency::Weekly => trans_choice('planner.series.weekly', $interval, [
                'count' => $interval,
                'days' => collect($rule->weekdays ?? [])->map(fn (mixed $day): string => (string) __('planner.weekdays.'.(int) $day))->implode(__('planner.picker.separator')),
            ]),
            PlanScheduleFrequency::Monthly => trans_choice('planner.series.monthly', $interval, [
                'count' => $interval,
                'day' => (int) ($rule->month_day ?? 1),
            ]),
            PlanScheduleFrequency::SelectedDates => trans_choice('planner.series.selected_dates', count($rule->selected_dates ?? []), ['count' => count($rule->selected_dates ?? [])]),
        };
    }

    public static function repeats(Plan $plan): bool
    {
        $rule = $plan->relationLoaded('scheduleRules') ? $plan->scheduleRules->first() : $plan->scheduleRules()->first();

        return $rule instanceof PlanScheduleRule && $rule->frequency !== PlanScheduleFrequency::Once;
    }
}
