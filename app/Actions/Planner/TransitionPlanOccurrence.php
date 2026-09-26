<?php

namespace App\Actions\Planner;

use App\Models\Actor;
use App\Models\PlanOccurrence;
use App\Models\PlanOccurrenceEvent;
use App\Models\User;
use App\PlanOccurrenceEventType;
use App\PlanOccurrenceStatus;
use App\PlanStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class TransitionPlanOccurrence
{
    public function start(PlanOccurrence $occurrence, User $user): PlanOccurrence
    {
        return $this->execute(
            $occurrence,
            $user,
            PlanOccurrenceStatus::InProgress,
            PlanOccurrenceEventType::Started,
        );
    }

    public function complete(PlanOccurrence $occurrence, User $user): PlanOccurrence
    {
        return $this->execute(
            $occurrence,
            $user,
            PlanOccurrenceStatus::Completed,
            PlanOccurrenceEventType::Completed,
        );
    }

    public function skip(PlanOccurrence $occurrence, User $user): PlanOccurrence
    {
        return $this->execute(
            $occurrence,
            $user,
            PlanOccurrenceStatus::Skipped,
            PlanOccurrenceEventType::Skipped,
        );
    }

    public function cancel(PlanOccurrence $occurrence, User $user): PlanOccurrence
    {
        return $this->execute(
            $occurrence,
            $user,
            PlanOccurrenceStatus::Cancelled,
            PlanOccurrenceEventType::Cancelled,
        );
    }

    private function execute(
        PlanOccurrence $occurrence,
        User $user,
        PlanOccurrenceStatus $status,
        PlanOccurrenceEventType $eventType,
    ): PlanOccurrence {
        $current = $this->currentUser($user);

        return DB::transaction(function () use ($occurrence, $current, $status, $eventType): PlanOccurrence {
            $locked = PlanOccurrence::query()
                ->with('plan')
                ->lockForUpdate()
                ->findOrFail($occurrence->id);

            Gate::forUser($current)->authorize('participate', $locked->plan);
            abort_unless($locked->plan->status === PlanStatus::Active, 422, 'Occurrence execution requires an active Plan.');

            $now = now();
            $actualStart = $locked->actual_start_at;
            $actualEnd = null;
            $completedAt = null;

            if ($status === PlanOccurrenceStatus::InProgress) {
                abort_unless($locked->status === PlanOccurrenceStatus::Scheduled, 422, 'Only a scheduled Occurrence can be started.');
                abort_unless($locked->canStartAt($now), 422, 'Occurrence may only be started inside its execution window.');
                $actualStart = $now;
            } elseif ($status === PlanOccurrenceStatus::Completed) {
                abort_unless($locked->status === PlanOccurrenceStatus::InProgress, 422, 'Only an in-progress Occurrence can be completed.');
                $actualEnd = $now;
                $completedAt = $now;
            } elseif ($status === PlanOccurrenceStatus::Skipped) {
                abort_unless($locked->status === PlanOccurrenceStatus::Scheduled, 422, 'Only a scheduled Occurrence can be skipped.');
            } elseif ($status === PlanOccurrenceStatus::Cancelled) {
                abort_unless(in_array($locked->status, [
                    PlanOccurrenceStatus::Scheduled,
                    PlanOccurrenceStatus::InProgress,
                ], true), 422, 'Only scheduled or in-progress Occurrences can be cancelled.');
                $actualEnd = $locked->status === PlanOccurrenceStatus::InProgress ? $now : null;
            }

            $locked->transition($status, $actualStart, $actualEnd, $completedAt);
            $actor = Actor::query()->findOrFail($current->actor->id);

            PlanOccurrenceEvent::query()->create([
                'plan_occurrence_id' => $locked->id,
                'actor_id' => $actor->id,
                'event_type' => $eventType,
                'payload' => [
                    'actual_start_at' => $actualStart?->toIso8601String(),
                    'actual_end_at' => $actualEnd?->toIso8601String(),
                ],
            ]);

            return $locked->fresh(['plan', 'events']);
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
