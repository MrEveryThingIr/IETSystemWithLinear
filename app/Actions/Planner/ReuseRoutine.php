<?php

namespace App\Actions\Planner;

use App\Models\Context;
use App\Models\Plan;
use App\Models\Planner\ExecutionPolicy;
use App\Models\PlanTemplate;
use App\Models\User;
use App\PlanScheduleFrequency;
use App\PlanTimingMode;
use App\Support\Parties\Party;
use App\Support\Planner\LabAuthority;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** Reuses definition shapes only: never copies occurrences, participants, evidence or reflections. */
final class ReuseRoutine
{
    public function capture(User $user, Plan $root, string $name): PlanTemplate
    {
        $actor = LabAuthority::actor($user);
        Gate::forUser($user)->authorize('manage', $root);

        return DB::transaction(function () use ($user, $root, $name, $actor): PlanTemplate {
            Context::query()->lockForUpdate()->findOrFail($root->context_id);
            $queue = [[$root, null]];
            $nodes = [];
            while ($queue !== []) {
                [$plan, $parent] = array_shift($queue);
                Gate::forUser($user)->authorize('manage', $plan);
                abort_if(count($nodes) >= 100, 422, 'A reusable routine may contain at most 100 activities.');
                $key = count($nodes);
                $rule = $plan->scheduleRules()->where('status', 'active')->latest('id')->first();
                abort_if($rule !== null && ! in_array($rule->frequency, PlanTemplate::FREQUENCIES, true), 422, 'Date-specific schedules cannot be reused as routine shapes.');
                $shape = $rule === null ? null : [
                    'execution_policy' => ExecutionPolicy::snapshot($rule),
                    'frequency' => $rule->frequency->value, 'timing_mode' => $rule->timing_mode->value,
                    'interval' => (int) $rule->interval, 'start_time' => substr($rule->start_time, 0, 5),
                    'duration_minutes' => (int) $rule->duration_minutes, 'weekdays' => $rule->weekdays,
                    'month_day' => $rule->month_day, 'occurrence_limit' => $rule->occurrence_limit,
                    'window_before_minutes' => (int) $rule->window_before_minutes,
                    'window_after_minutes' => (int) $rule->window_after_minutes,
                    'reminder_offsets' => $rule->reminders()->where('status', 'active')->pluck('minutes_before')->map(fn ($value): int => (int) $value)->all(),
                ];
                $nodes[] = ['parent' => $parent, 'title' => $plan->title, 'description' => $plan->description,
                    'structure_kind' => $plan->structure_kind, 'schedule' => $shape];
                foreach ($plan->childPlans()->orderBy('id')->get() as $child) {
                    $queue[] = [$child, $key];
                }
            }
            $template = app(SavePlanTemplate::class)->create($user, Party::of($actor), $name, [
                'frequency' => PlanScheduleFrequency::Once, 'timing_mode' => PlanTimingMode::Fixed,
                'interval' => 1, 'start_time' => '09:00', 'duration_minutes' => 60, 'weekdays' => null,
                'month_day' => null, 'occurrence_limit' => null, 'window_before_minutes' => 0,
                'window_after_minutes' => 0, 'reminder_offsets' => [], 'source_plan_uuid' => null,
            ]);
            $template->forceFill(['composition' => $nodes])->save();

            return $template;
        }, attempts: 3);
    }

    public function instantiate(User $user, PlanTemplate $template, Context $context, string $startsOn): Plan
    {
        LabAuthority::owns($user, (int) $template->owner_actor_id);
        abort_unless($template->status === 'active' && is_array($template->composition)
            && count($template->composition) >= 1 && count($template->composition) <= 100, 422);

        return DB::transaction(function () use ($user, $template, $context, $startsOn): Plan {
            $created = [];
            foreach ($template->composition as $key => $node) {
                abort_unless(is_array($node) && ($node['parent'] === null || isset($created[$node['parent']])), 422, 'Invalid routine composition.');
                $plan = app(CreatePlan::class)->execute($context, $user, $node['title'], $node['description'], metadata: ['routine_template_uuid' => $template->uuid]);
                $plan->forceFill(['structure_kind' => $node['structure_kind']])->save();
                if ($node['parent'] !== null) {
                    app(ComposePlan::class)->execute($user, $plan, $created[$node['parent']]);
                }
                $created[$key] = $plan;
                $shape = $node['schedule'];
                if ($shape !== null) {
                    if ((int) data_get($shape, 'execution_policy.version', 0) > 0) {
                        app(ConfigureExecutionPolicy::class)->execute($user, $plan, $shape['execution_policy']['window_style'], $shape['execution_policy']['reporting_days']);
                    }
                    app(CreatePlanScheduleRule::class)->execute($plan, $user,
                        PlanScheduleFrequency::from($shape['frequency']), $startsOn, $shape['start_time'], $shape['duration_minutes'],
                        interval: $shape['interval'], weekdays: $shape['weekdays'] ?? [], occurrenceLimit: $shape['occurrence_limit'],
                        windowBeforeMinutes: $shape['window_before_minutes'], windowAfterMinutes: $shape['window_after_minutes'],
                        reminderOffsets: $shape['reminder_offsets'], timingMode: PlanTimingMode::from($shape['timing_mode']), monthDay: $shape['month_day']);
                }
            }

            return $created[0];
        }, attempts: 3);
    }
}
