<?php

namespace App\Actions\Planner;

use App\Models\Actor;
use App\Models\PlanOccurrence;
use App\Models\PlanOccurrenceEvent;
use App\Models\PlanOccurrencePrerequisiteCheck;
use App\Models\PlanPrerequisite;
use App\Models\User;
use App\PlanOccurrenceEventType;
use App\PlanOccurrenceStatus;
use App\PlanStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SetPlanOccurrencePrerequisite
{
    public function execute(
        PlanOccurrence $occurrence,
        PlanPrerequisite $prerequisite,
        User $user,
        bool $completed,
    ): PlanOccurrencePrerequisiteCheck {
        $current = $this->currentUser($user);

        return DB::transaction(function () use ($occurrence, $prerequisite, $current, $completed): PlanOccurrencePrerequisiteCheck {
            $locked = PlanOccurrence::query()
                ->with('plan')
                ->lockForUpdate()
                ->findOrFail($occurrence->id);

            Gate::forUser($current)->authorize('participate', $locked->plan);
            abort_unless($locked->plan->status === PlanStatus::Active, 422, 'Occurrence readiness requires an active Plan.');
            abort_unless($locked->status === PlanOccurrenceStatus::Scheduled, 422, 'Prerequisites can only be changed before an Occurrence starts.');
            abort_if($locked->executionPhase() === 'passed', 422, 'Prerequisites cannot be changed after the execution window has passed.');

            $lockedPrerequisite = PlanPrerequisite::query()->lockForUpdate()->findOrFail($prerequisite->id);
            abort_unless((int) $lockedPrerequisite->plan_id === (int) $locked->plan_id, 422, 'Prerequisite does not belong to this Plan.');

            $actor = Actor::query()->lockForUpdate()->findOrFail($current->actor->id);

            $check = PlanOccurrencePrerequisiteCheck::query()->firstOrCreate([
                'plan_occurrence_id' => $locked->id,
                'plan_prerequisite_id' => $lockedPrerequisite->id,
            ]);

            $check->forceFill([
                'completed_by_actor_id' => $completed ? $actor->id : null,
                'completed_at' => $completed ? now() : null,
            ])->save();

            PlanOccurrenceEvent::query()->create([
                'plan_occurrence_id' => $locked->id,
                'actor_id' => $actor->id,
                'event_type' => PlanOccurrenceEventType::PrerequisiteUpdated,
                'payload' => [
                    'plan_prerequisite_uuid' => $lockedPrerequisite->uuid,
                    'completed' => $completed,
                ],
            ]);

            return $check->fresh(['prerequisite', 'completedBy.user']);
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
