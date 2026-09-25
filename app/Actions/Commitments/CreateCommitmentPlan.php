<?php

namespace App\Actions\Commitments;

use App\Actions\Planner\CreatePlan;
use App\Actions\Planner\CreatePlanScheduleRule;
use App\CommitmentEventType;
use App\Models\Actor;
use App\Models\Commitment;
use App\Models\CommitmentEvent;
use App\Models\CommitmentPlanBinding;
use App\Models\Context;
use App\Models\Plan;
use App\Models\User;
use App\PlanScheduleFrequency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CreateCommitmentPlan
{
    public function __construct(
        private readonly CreatePlan $createPlan,
        private readonly CreatePlanScheduleRule $createRule,
    ) {}

    /**
     * @param  list<int>  $weekdays
     * @param  list<string>  $selectedDates
     * @param  list<int>  $reminderOffsets
     */
    public function execute(
        Commitment $commitment,
        User $user,
        PlanScheduleFrequency $frequency,
        string $startsOn,
        string $startTime,
        int $durationMinutes,
        int $interval = 1,
        array $weekdays = [],
        array $selectedDates = [],
        ?string $endsOn = null,
        ?int $occurrenceLimit = null,
        int $windowBeforeMinutes = 0,
        int $windowAfterMinutes = 0,
        array $reminderOffsets = [],
    ): Plan {
        $current = $this->currentUser($user);
        Gate::forUser($current)->authorize('manage', $commitment);

        return DB::transaction(function () use (
            $commitment,
            $current,
            $frequency,
            $startsOn,
            $startTime,
            $durationMinutes,
            $interval,
            $weekdays,
            $selectedDates,
            $endsOn,
            $occurrenceLimit,
            $windowBeforeMinutes,
            $windowAfterMinutes,
            $reminderOffsets,
        ): Plan {
            $locked = Commitment::query()
                ->with([
                    'contractVersion.contract.contextBinding.context',
                    'obligor.user',
                    'beneficiary.user',
                    'planBinding',
                ])
                ->lockForUpdate()
                ->findOrFail($commitment->id);

            Gate::forUser($current)->authorize('manage', $locked);
            abort_if(
                $locked->planBinding instanceof CommitmentPlanBinding,
                422,
                'This Commitment is already bound to a Plan.',
            );

            $context = $locked->contractVersion->contract->contextBinding?->context;
            abort_unless($context instanceof Context, 500, 'Contract Context is missing.');

            $participants = collect([$locked->obligor, $locked->beneficiary])
                ->unique('id')
                ->reject(fn (Actor $actor): bool => (int) $actor->id === (int) $current->actor->id)
                ->map(fn (Actor $actor): array => [
                    'actor' => $actor,
                    'role' => (int) $actor->id === (int) $locked->obligor_actor_id
                        ? 'performer'
                        : 'reviewer',
                ])
                ->values()
                ->all();

            $plan = $this->createPlan->execute(
                $context,
                $current,
                $locked->title,
                $locked->description,
                participants: $participants,
                originType: 'commitment',
                originUuid: $locked->uuid,
                metadata: [
                    'commitment_uuid' => $locked->uuid,
                    'contract_version_uuid' => $locked->contractVersion->uuid,
                    'quantity' => $locked->quantity,
                    'unit' => $locked->unit,
                ],
            );

            $this->createRule->execute(
                $plan,
                $current,
                $frequency,
                $startsOn,
                $startTime,
                $durationMinutes,
                $interval,
                $weekdays,
                $selectedDates,
                $endsOn,
                $occurrenceLimit,
                $windowBeforeMinutes,
                $windowAfterMinutes,
                $reminderOffsets,
            );

            $binding = CommitmentPlanBinding::query()->create([
                'commitment_id' => $locked->id,
                'plan_id' => $plan->id,
                'bound_by_actor_id' => $current->actor->id,
            ]);

            CommitmentEvent::query()->create([
                'commitment_id' => $locked->id,
                'actor_id' => $current->actor->id,
                'event_type' => CommitmentEventType::PlannerBound,
                'payload' => [
                    'binding_uuid' => $binding->uuid,
                    'plan_uuid' => $plan->uuid,
                    'occurrence_count' => $plan->occurrences()->count(),
                ],
            ]);

            return $plan->fresh([
                'context',
                'participants.actor.user',
                'scheduleRules',
                'occurrences',
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
