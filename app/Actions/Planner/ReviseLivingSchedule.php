<?php

namespace App\Actions\Planner;

use App\Models\Plan;
use App\Models\PlanEvent;
use App\Models\Planner\Experiment;
use App\Models\Planner\ExperimentAdjustment;
use App\Models\PlanOccurrence;
use App\Models\PlanOccurrenceEvent;
use App\Models\User;
use App\PlanEventType;
use App\PlanOccurrenceEventType;
use App\PlanOccurrenceStatus;
use App\PlanScheduleFrequency;
use App\PlanTimingMode;
use App\Support\Planner\LabAuthority;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

/** Explicit future revisions preserve old definitions, targets and all execution history. */
final class ReviseLivingSchedule
{
    public function execute(User $user, Plan $plan, string $scope, string $startsOn, string $time, int $minutes, ?PlanOccurrence $occurrence = null, ?ExperimentAdjustment $adjustment = null): void
    {
        $actor = LabAuthority::actor($user);
        Gate::forUser($user)->authorize('manage', $plan);
        Validator::make(compact('scope', 'startsOn', 'time', 'minutes'), [
            'scope' => ['required', 'in:one,future'], 'startsOn' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'], 'minutes' => ['integer', 'between:1,10080'],
        ])->validate();
        $date = CarbonImmutable::parse($startsOn, $plan->timezone);
        abort_if($date->lt(CarbonImmutable::now($plan->timezone)->startOfDay()) || $date->gt(now($plan->timezone)->addDays(90)), 422, __('living_planner.revision_range'));
        abort_unless(CarbonImmutable::parse($startsOn.' '.$time, $plan->timezone)->isFuture(), 422, __('living_planner.revision_range'));

        DB::transaction(function () use ($user, $plan, $scope, $startsOn, $time, $minutes, $occurrence, $adjustment, $actor, $date): void {
            Plan::query()->lockForUpdate()->findOrFail($plan->id);
            if ($adjustment !== null) {
                $decision = ExperimentAdjustment::query()->lockForUpdate()->findOrFail($adjustment->id);
                $experiment = app(PersonalExperiment::class)->authorize($user, Experiment::query()->findOrFail($decision->experiment_id));
                abort_unless($decision->decision === 'approved', 422);
                abort_unless($experiment->plan_id === $plan->id || ($experiment->purpose_id !== null
                    && DB::table('planner_purpose_contributions')->where('purpose_id', $experiment->purpose_id)->where('plan_id', $plan->id)->exists()), 403);
                if ($plan->events()->where('event_type', PlanEventType::ScheduleRuleCreated)->where('payload->adjustment_uuid', $decision->uuid)->exists()) {
                    return;
                }
            }
            $rule = $plan->scheduleRules()->where('status', 'active')->latest('id')->first();
            $frequency = PlanScheduleFrequency::Once;
            if ($scope === 'one') {
                abort_unless($occurrence !== null && $occurrence->plan_id === $plan->id, 422);
                $locked = PlanOccurrence::query()->lockForUpdate()->findOrFail($occurrence->id);
                abort_unless($locked->status === PlanOccurrenceStatus::Scheduled && $locked->scheduled_start_at->isFuture()
                    && ! $locked->participations()->exists(), 422, __('living_planner.action_unavailable'));
                $locked->transition(PlanOccurrenceStatus::Cancelled);
                PlanOccurrenceEvent::query()->create(['plan_occurrence_id' => $locked->id, 'actor_id' => $actor->id,
                    'event_type' => PlanOccurrenceEventType::Cancelled, 'payload' => ['reason' => 'explicit_single_revision'], 'effective_at' => now()]);
            } elseif ($rule !== null) {
                abort_if($rule->frequency === PlanScheduleFrequency::SelectedDates, 422, __('living_planner.revise_selected'));
                abort_if($rule->ends_on !== null && $rule->ends_on->lt($date), 422, __('living_planner.action_unavailable'));
                app(MaterializePlanOccurrences::class)->execute($rule, CarbonImmutable::now($plan->timezone)->startOfDay(), $date->endOfDay());
                abort_if($rule->occurrences()->where('scheduled_start_at', '>=', $date->utc())->whereHas('participations')->exists(), 422, __('living_planner.action_unavailable'));
                $frequency = $rule->frequency;
                $rule->cancel();
                foreach ($rule->occurrences()->where('scheduled_start_at', '>=', $date->utc())->lockForUpdate()->get() as $future) {
                    if ($future->status !== PlanOccurrenceStatus::Scheduled || $future->scheduled_start_at->isPast()) {
                        continue;
                    }
                    $future->transition(PlanOccurrenceStatus::Cancelled);
                    PlanOccurrenceEvent::query()->create(['plan_occurrence_id' => $future->id, 'actor_id' => $actor->id,
                        'event_type' => PlanOccurrenceEventType::Cancelled, 'effective_at' => now(), 'payload' => ['reason' => 'explicit_future_revision']]);
                }
            }
            $replacement = app(CreatePlanScheduleRule::class)->execute($plan, $user, $frequency, $startsOn, $time, $minutes,
                interval: $rule->interval ?? 1, weekdays: $rule->weekdays ?? [],
                endsOn: $scope === 'future' ? $rule?->ends_on?->toDateString() : null,
                occurrenceLimit: $scope !== 'future' || $rule?->occurrence_limit === null ? null : max(1, $rule->occurrence_limit - $rule->occurrences()->where('scheduled_start_at', '<', $date->utc())->count()),
                reminderOffsets: $rule === null ? [] : $rule->reminders()->where('status', 'active')->pluck('minutes_before')->all(),
                windowBeforeMinutes: $rule->window_before_minutes ?? 0, windowAfterMinutes: $rule->window_after_minutes ?? 0,
                timingMode: $rule->timing_mode ?? PlanTimingMode::Fixed, monthDay: $rule?->month_day);
            PlanEvent::query()->create(['plan_id' => $plan->id, 'actor_id' => $actor->id, 'event_type' => PlanEventType::ScheduleRuleCreated,
                'payload' => ['revision_scope' => $scope, 'previous_rule_uuid' => $rule?->uuid, 'replacement_rule_uuid' => $replacement->uuid,
                    'adjustment_uuid' => $adjustment?->uuid, 'effective_on' => $startsOn]]);
        }, attempts: 3);
    }
}
