<?php

namespace App\Actions\Planner;

use App\Models\Actor;
use App\Models\Plan;
use App\Models\PlanEvent;
use App\Models\PlanOccurrenceEvent;
use App\Models\User;
use App\PlanEventType;
use App\PlanOccurrenceEventType;
use App\PlanOccurrenceStatus;
use App\PlanScheduleRuleStatus;
use App\PlanStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class TransitionPlan
{
    public function execute(Plan $plan, User $user, PlanStatus $status): Plan
    {
        $current = $this->currentUser($user);

        return DB::transaction(function () use ($plan, $current, $status): Plan {
            $locked = Plan::query()
                ->with(['scheduleRules', 'occurrences'])
                ->lockForUpdate()
                ->findOrFail($plan->id);

            Gate::forUser($current)->authorize('manage', $locked);

            $from = $locked->status;

            if ($status === PlanStatus::Completed) {
                abort_if(
                    $locked->occurrences->contains(
                        fn ($occurrence): bool => $occurrence->status === PlanOccurrenceStatus::InProgress,
                    ),
                    422,
                    __('planner.validation.finish_active_occurrences_first'),
                );
            }

            $locked->applyStatus($status);

            $eventType = match ($status) {
                PlanStatus::Paused => PlanEventType::Paused,
                PlanStatus::Active => PlanEventType::Resumed,
                PlanStatus::Completed => PlanEventType::Completed,
                PlanStatus::Cancelled => PlanEventType::Cancelled,
            };

            $actor = Actor::query()->findOrFail($current->actor->id);

            if (in_array($status, [PlanStatus::Completed, PlanStatus::Cancelled], true)) {
                foreach ($locked->scheduleRules as $rule) {
                    if ($rule->status === PlanScheduleRuleStatus::Active) {
                        $rule->cancel();
                    }
                }

                foreach ($locked->occurrences as $occurrence) {
                    $cancellableStatuses = $status === PlanStatus::Completed
                        ? [PlanOccurrenceStatus::Scheduled]
                        : [PlanOccurrenceStatus::Scheduled, PlanOccurrenceStatus::InProgress];

                    if (! in_array($occurrence->status, $cancellableStatuses, true)) {
                        continue;
                    }

                    $actualEnd = $occurrence->status === PlanOccurrenceStatus::InProgress ? now() : null;

                    $occurrence->transition(
                        PlanOccurrenceStatus::Cancelled,
                        actualStartAt: $occurrence->actual_start_at,
                        actualEndAt: $actualEnd,
                    );

                    PlanOccurrenceEvent::query()->create([
                        'plan_occurrence_id' => $occurrence->id,
                        'actor_id' => $actor->id,
                        'event_type' => PlanOccurrenceEventType::Cancelled,
                        'payload' => ['reason' => 'plan_'.$status->value],
                    ]);
                }
            }

            PlanEvent::query()->create([
                'plan_id' => $locked->id,
                'actor_id' => $actor->id,
                'event_type' => $eventType,
                'payload' => ['from' => $from->value, 'to' => $status->value],
            ]);

            return $locked->fresh([
                'scheduleRules',
                'occurrences',
                'events',
            ]);
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
