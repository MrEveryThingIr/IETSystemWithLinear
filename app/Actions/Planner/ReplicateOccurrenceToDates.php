<?php

namespace App\Actions\Planner;

use App\Models\Plan;
use App\Models\PlanOccurrence;
use App\Models\User;
use App\PlanAttentionMode;
use App\PlanOccurrenceStatus;
use App\PlanScheduleFrequency;
use App\PlanTimingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Throwable;

class ReplicateOccurrenceToDates
{
    public function __construct(
        private readonly CreatePlan $createPlan,
        private readonly CreatePlanScheduleRule $createScheduleRule,
    ) {}

    /**
     * @param  list<string>  $dates
     * @return Collection<int, Plan>
     */
    public function execute(PlanOccurrence $source, User $user, array $dates): Collection
    {
        $source = PlanOccurrence::query()
            ->with(['plan.context', 'scheduleRule'])
            ->findOrFail($source->id);

        Gate::forUser($user)->authorize('manage', $source->plan);

        abort_unless(
            data_get($source->plan->metadata, 'planning_studio') === 'baseline',
            422,
            __('planning_baseline.tools.repeat.baseline_only'),
        );
        abort_unless(
            $source->scheduleRule?->frequency === PlanScheduleFrequency::Once,
            422,
            __('planning_baseline.tools.repeat.once_only'),
        );
        abort_if(
            $source->status === PlanOccurrenceStatus::Cancelled,
            422,
            __('planning_baseline.tools.repeat.cancelled_source'),
        );

        $timezone = $source->plan->timezone;
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $sourceDate = $source->local_date->format('Y-m-d');

        $normalizedDates = collect($dates)
            ->map(fn (mixed $date): string => trim((string) $date))
            ->filter()
            ->unique()
            ->sort()
            ->values();

        abort_if($normalizedDates->isEmpty(), 422, __('planning_baseline.tools.repeat.choose_dates'));
        abort_if($normalizedDates->count() > 62, 422, __('planning_baseline.tools.repeat.too_many_dates'));

        foreach ($normalizedDates as $date) {
            $parsed = $this->date($date, $timezone);

            abort_unless($parsed->greaterThan($today), 422, __('planning_baseline.tools.repeat.future_only'));
            abort_if($date === $sourceDate, 422, __('planning_baseline.tools.repeat.other_instances_only'));
        }

        $rule = $source->scheduleRule;
        $mode = $rule->timing_mode ?? PlanTimingMode::Fixed;
        $startTime = $mode === PlanTimingMode::FlexibleDay
            ? '00:00'
            : substr((string) $rule->start_time, 0, 5);
        $duration = $mode === PlanTimingMode::FlexibleDay
            ? 1440
            : (int) $rule->duration_minutes;

        return DB::transaction(function () use (
            $source,
            $user,
            $normalizedDates,
            $mode,
            $startTime,
            $duration,
        ): Collection {
            return $normalizedDates->map(function (string $date) use (
                $source,
                $user,
                $mode,
                $startTime,
                $duration,
            ): Plan {
                $metadata = $source->plan->metadata ?? [];
                $metadata['planning_studio'] = 'baseline';
                $metadata['replicated_from_plan_uuid'] = (string) data_get(
                    $source->plan->metadata,
                    'replicated_from_plan_uuid',
                    $source->plan->uuid,
                );
                $metadata['replicated_from_occurrence_uuid'] = $source->uuid;
                $metadata['replication_tool'] = 'repeat_time_window';

                $copy = $this->createPlan->execute(
                    $source->plan->context,
                    $user,
                    $source->plan->title,
                    $source->plan->description,
                    $source->plan->timezone,
                    metadata: $metadata,
                    attentionMode: $source->plan->attention_mode ?? PlanAttentionMode::Exclusive,
                );

                $this->createScheduleRule->execute(
                    $copy,
                    $user,
                    PlanScheduleFrequency::Once,
                    $date,
                    $startTime,
                    $duration,
                    timingMode: $mode,
                );

                return $copy->fresh(['scheduleRules', 'occurrences']);
            });
        }, attempts: 3);
    }

    private function date(string $date, string $timezone): CarbonImmutable
    {
        try {
            $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $date, $timezone);
        } catch (Throwable) {
            abort(422, __('planning_baseline.tools.repeat.invalid_date'));
        }

        abort_unless(
            $parsed instanceof CarbonImmutable && $parsed->format('Y-m-d') === $date,
            422,
            __('planning_baseline.tools.repeat.invalid_date'),
        );

        return $parsed;
    }
}
