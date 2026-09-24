<?php

namespace App\Actions\Planner;

use App\Models\Actor;
use App\Models\PlanEvent;
use App\Models\PlanOccurrenceEvent;
use App\Models\PlanScheduleRule;
use App\Models\User;
use App\PlanEventType;
use App\PlanOccurrenceEventType;
use App\PlanOccurrenceStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CancelPlanScheduleRule
{
    public function execute(PlanScheduleRule $rule, User $user): PlanScheduleRule
    {
        $current = $this->currentUser($user);

        return DB::transaction(function () use ($rule, $current): PlanScheduleRule {
            $locked = PlanScheduleRule::query()
                ->with(['plan', 'occurrences'])
                ->lockForUpdate()
                ->findOrFail($rule->id);

            Gate::forUser($current)->authorize('manage', $locked->plan);
            $locked->cancel();
            $actor = Actor::query()->findOrFail($current->actor->id);

            foreach ($locked->occurrences as $occurrence) {
                if ($occurrence->status !== PlanOccurrenceStatus::Scheduled || $occurrence->scheduled_start_at->isPast()) {
                    continue;
                }

                $occurrence->transition(PlanOccurrenceStatus::Cancelled);
                PlanOccurrenceEvent::query()->create([
                    'plan_occurrence_id' => $occurrence->id,
                    'actor_id' => $actor->id,
                    'event_type' => PlanOccurrenceEventType::Cancelled,
                    'payload' => ['reason' => 'schedule_rule_cancelled'],
                ]);
            }

            PlanEvent::query()->create([
                'plan_id' => $locked->plan_id,
                'actor_id' => $actor->id,
                'event_type' => PlanEventType::ScheduleRuleCancelled,
                'payload' => ['schedule_rule_uuid' => $locked->uuid],
            ]);

            return $locked->fresh(['plan', 'occurrences']);
        }, attempts: 3);
    }

    private function currentUser(User $user): User
    {
        $current = User::query()->with('actor')->find($user->id);
        abort_unless(
            $current instanceof User
            && $current->status === 'active'
            && $current->email_verified_at !== null
            && $current->actor instanceof Actor
            && $current->actor->status === 'active',
            403,
        );

        return $current;
    }
}
