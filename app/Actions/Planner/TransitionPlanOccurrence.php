<?php

namespace App\Actions\Planner;

use App\Models\Actor;
use App\Models\Planner\OccurrenceParticipation;
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

    /**
     * "Done" for something that is simply handled (a paid installment, a
     * cashed cheque): marks it started and completed now.
     */
    public function markDone(PlanOccurrence $occurrence, User $user): PlanOccurrence
    {
        abort_if($this->usesLivingExecution($occurrence), 422, 'Record your own execution or report actual times in Plans.');
        $current = $this->currentUser($user);

        return DB::transaction(function () use ($occurrence, $current): PlanOccurrence {
            $locked = PlanOccurrence::query()->with('plan')->lockForUpdate()->findOrFail($occurrence->id);
            Gate::forUser($current)->authorize('participate', $locked->plan);
            abort_unless(in_array($locked->status, [PlanOccurrenceStatus::Scheduled, PlanOccurrenceStatus::InProgress], true), 422, __('planner.validation.cancellable_occurrence'));
            $actor = Actor::query()->findOrFail($current->actor->id);
            $now = now();

            if ($locked->status === PlanOccurrenceStatus::Scheduled) {
                $locked->transition(PlanOccurrenceStatus::InProgress, $now);
                PlanOccurrenceEvent::query()->create(['plan_occurrence_id' => $locked->id, 'actor_id' => $actor->id, 'event_type' => PlanOccurrenceEventType::Started, 'payload' => ['actual_start_at' => $now->toIso8601String(), 'marked_done' => true]]);
            }
            $locked->refresh()->transition(PlanOccurrenceStatus::Completed, $locked->actual_start_at ?? $now, $now, $now);
            PlanOccurrenceEvent::query()->create(['plan_occurrence_id' => $locked->id, 'actor_id' => $actor->id, 'event_type' => PlanOccurrenceEventType::Completed, 'payload' => ['actual_end_at' => $now->toIso8601String(), 'marked_done' => true]]);

            return $locked->fresh();
        }, attempts: 3);
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
        if ($this->usesLivingExecution($occurrence)) {
            $action = match ($status) {
                PlanOccurrenceStatus::InProgress => 'start',
                PlanOccurrenceStatus::Completed => 'finish',
                PlanOccurrenceStatus::Skipped => 'report',
                PlanOccurrenceStatus::Cancelled => 'cancel',
                default => abort(422),
            };

            return app(ExecuteLivingOccurrence::class)->execute($user, $occurrence, $action,
                'legacy-'.$action.'-'.$user->id, $action === 'report' ? ['outcome' => 'missed'] : []);
        }
        $current = $this->currentUser($user);

        return DB::transaction(function () use ($occurrence, $current, $status, $eventType): PlanOccurrence {
            $locked = PlanOccurrence::query()
                ->with(['plan.prerequisites', 'prerequisiteChecks'])
                ->lockForUpdate()
                ->findOrFail($occurrence->id);

            Gate::forUser($current)->authorize('participate', $locked->plan);
            abort_unless($locked->plan->status === PlanStatus::Active, 422, __('planner.validation.active_plan_required'));

            $now = now();
            $actualStart = $locked->actual_start_at;
            $actualEnd = null;
            $completedAt = null;

            if ($status === PlanOccurrenceStatus::InProgress) {
                abort_unless($locked->status === PlanOccurrenceStatus::Scheduled, 422, __('planner.validation.scheduled_to_start'));
                abort_unless($locked->canStartAt($now), 422, __('planner.validation.start_window'));
                abort_unless($locked->prerequisitesSatisfied(), 422, __('planner.validation.prerequisites_incomplete'));
                $actualStart = $now;
            } elseif ($status === PlanOccurrenceStatus::Completed) {
                abort_unless(
                    $locked->status === PlanOccurrenceStatus::InProgress,
                    422,
                    __('planner.validation.start_before_complete'),
                );
                $actualEnd = $now;
                $completedAt = $now;
            } elseif ($status === PlanOccurrenceStatus::Skipped) {
                abort_unless($locked->status === PlanOccurrenceStatus::Scheduled, 422, __('planner.validation.scheduled_to_skip'));
            } elseif ($status === PlanOccurrenceStatus::Cancelled) {
                abort_unless(in_array($locked->status, [
                    PlanOccurrenceStatus::Scheduled,
                    PlanOccurrenceStatus::InProgress,
                ], true), 422, __('planner.validation.cancellable_occurrence'));
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

            return $locked->fresh([
                'plan.prerequisites',
                'prerequisiteChecks',
                'expenses.monetaryUnit',
                'events',
            ]);
        }, attempts: 3);
    }

    private function usesLivingExecution(PlanOccurrence $occurrence): bool
    {
        $current = $occurrence->fresh();

        return (int) data_get($current->policy_snapshot, 'version', 0) > 0
            || OccurrenceParticipation::query()->where('plan_occurrence_id', $current->id)->exists();
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
