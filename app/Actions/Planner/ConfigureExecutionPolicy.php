<?php

namespace App\Actions\Planner;

use App\Models\Plan;
use App\Models\PlanEvent;
use App\Models\Planner\ExecutionPolicy;
use App\Models\User;
use App\PlanEventType;
use App\Support\Planner\LabAuthority;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class ConfigureExecutionPolicy
{
    public function execute(User $user, Plan $plan, string $style, int $reportingDays = 30): ExecutionPolicy
    {
        $actor = LabAuthority::actor($user);
        Gate::forUser($user)->authorize('manage', $plan);
        abort_unless(in_array($style, ['hard', 'flexible', 'deadline'], true) && $reportingDays >= 0 && $reportingDays <= 365, 422);

        return DB::transaction(function () use ($plan, $actor, $style, $reportingDays): ExecutionPolicy {
            Plan::query()->lockForUpdate()->findOrFail($plan->id);
            $policy = ExecutionPolicy::query()->create(['plan_id' => $plan->id, 'created_by_actor_id' => $actor->id,
                'version' => (int) ExecutionPolicy::query()->where('plan_id', $plan->id)->max('version') + 1,
                'window_style' => $style, 'reporting_days' => $reportingDays]);
            PlanEvent::query()->create(['plan_id' => $plan->id, 'actor_id' => $actor->id, 'event_type' => PlanEventType::PolicyChanged,
                'payload' => ['policy_uuid' => $policy->uuid, 'version' => $policy->version, 'scope' => 'new_schedule_rules']]);

            return $policy;
        }, attempts: 3);
    }
}
