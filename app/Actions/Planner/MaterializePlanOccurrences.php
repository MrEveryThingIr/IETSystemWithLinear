<?php

namespace App\Actions\Planner;

use App\Models\PlanOccurrence;
use App\Models\PlanScheduleRule;
use App\PlanScheduleFrequency;
use App\PlanScheduleRuleStatus;
use App\PlanStatus;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class MaterializePlanOccurrences
{
    public function execute(
        PlanScheduleRule $rule,
        CarbonInterface $from,
        CarbonInterface $through,
    ): int {
        $current = PlanScheduleRule::query()->with('plan')->findOrFail($rule->id);

        if (
            $current->status !== PlanScheduleRuleStatus::Active
            || $current->plan->status !== PlanStatus::Active
        ) {
            return 0;
        }

        $timezone = $current->timezone;
        $rangeStart = CarbonImmutable::parse($from->toIso8601String())
            ->setTimezone($timezone)
            ->startOfDay();
        $rangeEnd = CarbonImmutable::parse($through->toIso8601String())
            ->setTimezone($timezone)
            ->endOfDay();

        abort_if($rangeEnd->lt($rangeStart), 422, 'Planner materialization range is invalid.');

        $scheduleStart = CarbonImmutable::parse(
            $current->starts_on->format('Y-m-d'),
            $timezone,
        )->startOfDay();

        $firstDate = $rangeStart->greaterThan($scheduleStart) ? $rangeStart : $scheduleStart;
        $lastDate = $rangeEnd;

        if ($current->ends_on !== null) {
            $endsOn = CarbonImmutable::parse($current->ends_on->format('Y-m-d'), $timezone)->endOfDay();

            if ($endsOn->lessThan($lastDate)) {
                $lastDate = $endsOn;
            }
        }

        if ($lastDate->lt($firstDate)) {
            return 0;
        }

        $ordinal = $current->occurrence_limit !== null
            ? $this->countMatchesBefore($current, $scheduleStart, $firstDate)
            : 0;
        $created = 0;

        for ($date = $firstDate->startOfDay(); $date->lte($lastDate); $date = $date->addDay()) {
            if (! $this->matches($current, $scheduleStart, $date)) {
                continue;
            }

            $ordinal++;

            if ($current->occurrence_limit !== null && $ordinal > $current->occurrence_limit) {
                break;
            }

            $localStart = CarbonImmutable::parse(
                $date->format('Y-m-d').' '.substr($current->start_time, 0, 5),
                $timezone,
            );
            $localEnd = $localStart->addMinutes($current->duration_minutes);

            $occurrence = PlanOccurrence::query()->firstOrCreate(
                [
                    'schedule_rule_id' => $current->id,
                    'scheduled_start_at' => $localStart->utc(),
                ],
                [
                    'plan_id' => $current->plan_id,
                    'local_date' => $date->format('Y-m-d'),
                    'scheduled_end_at' => $localEnd->utc(),
                    'window_start_at' => $localStart->subMinutes($current->window_before_minutes)->utc(),
                    'window_end_at' => $localStart->addMinutes($current->window_after_minutes)->utc(),
                    'timezone' => $timezone,
                    'origin_type' => $current->plan->origin_type,
                    'origin_uuid' => $current->plan->origin_uuid,
                    'metadata' => [],
                ],
            );

            if ($occurrence->wasRecentlyCreated) {
                $created++;
            }
        }

        return $created;
    }

    private function countMatchesBefore(
        PlanScheduleRule $rule,
        CarbonImmutable $scheduleStart,
        CarbonImmutable $before,
    ): int {
        $count = 0;

        for ($date = $scheduleStart; $date->lt($before); $date = $date->addDay()) {
            if ($rule->ends_on !== null && $date->gt(
                CarbonImmutable::parse($rule->ends_on->format('Y-m-d'), $rule->timezone),
            )) {
                break;
            }

            if ($this->matches($rule, $scheduleStart, $date)) {
                $count++;

                if ($rule->occurrence_limit !== null && $count >= $rule->occurrence_limit) {
                    break;
                }
            }
        }

        return $count;
    }

    private function matches(
        PlanScheduleRule $rule,
        CarbonImmutable $scheduleStart,
        CarbonImmutable $date,
    ): bool {
        if ($date->lt($scheduleStart)) {
            return false;
        }

        return match ($rule->frequency) {
            PlanScheduleFrequency::Once => $date->isSameDay($scheduleStart),
            PlanScheduleFrequency::SelectedDates => in_array(
                $date->format('Y-m-d'),
                $rule->selected_dates ?? [],
                true,
            ),
            PlanScheduleFrequency::Daily => ((int) $scheduleStart->diffInDays($date)) % $rule->interval === 0,
            PlanScheduleFrequency::Weekly => $this->matchesWeekly($rule, $scheduleStart, $date),
        };
    }

    private function matchesWeekly(
        PlanScheduleRule $rule,
        CarbonImmutable $scheduleStart,
        CarbonImmutable $date,
    ): bool {
        $weekdays = array_map('intval', $rule->weekdays ?? []);

        if (! in_array($date->isoWeekday(), $weekdays, true)) {
            return false;
        }

        $anchorWeek = $scheduleStart->startOfWeek(CarbonInterface::MONDAY);
        $candidateWeek = $date->startOfWeek(CarbonInterface::MONDAY);
        $weeks = intdiv((int) $anchorWeek->diffInDays($candidateWeek), 7);

        return $weeks % $rule->interval === 0;
    }
}
