<?php

namespace App\Actions\Commitments;

use App\CommitmentEventType;
use App\Models\Actor;
use App\Models\Commitment;
use App\Models\CommitmentEvent;
use App\Models\CommitmentPlanBinding;
use App\Models\Context;
use App\Models\Plan;
use App\Models\User;
use App\PlanStatus;
use App\Support\RequestMemo;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Connects work to a plan that already exists in the same deal, so both
 * sides see the same schedule. Plans from anyone's private planner are not
 * offered: connecting one would show its occurrences to the other side.
 */
class BindExistingPlan
{
    /** @return Collection<int, Plan> */
    public function candidates(User $user, Commitment $commitment): Collection
    {
        $context = $this->context($commitment);
        if (! $context instanceof Context || ! Gate::forUser($user)->allows('manage', $commitment)) {
            return new Collection;
        }

        return Plan::query()
            ->where('context_id', $context->id)
            ->where('status', PlanStatus::Active->value)
            ->whereNotIn('id', CommitmentPlanBinding::query()->select('plan_id'))
            ->orderBy('title')
            ->limit(50)
            ->get()
            ->filter(fn (Plan $plan): bool => Gate::forUser($user)->allows('manage', $plan))
            ->values();
    }

    public function execute(User $user, Commitment $commitment, Plan $plan): CommitmentPlanBinding
    {
        Gate::forUser($user)->authorize('manage', $commitment);
        abort_unless($this->candidates($user, $commitment)->contains('id', $plan->id), 422, 'This plan cannot be connected to this work.');
        $actor = RequestMemo::user($user)?->actor;
        abort_unless($actor instanceof Actor, 403);

        return DB::transaction(function () use ($commitment, $plan, $actor): CommitmentPlanBinding {
            $locked = Commitment::query()->with('planBinding')->lockForUpdate()->findOrFail($commitment->id);
            abort_if($locked->planBinding instanceof CommitmentPlanBinding, 422, 'This Commitment is already bound to a Plan.');
            abort_if(CommitmentPlanBinding::query()->where('plan_id', $plan->id)->lockForUpdate()->exists(), 422, 'This plan already tracks other work.');

            $binding = CommitmentPlanBinding::query()->create([
                'commitment_id' => $locked->id,
                'plan_id' => $plan->id,
                'bound_by_actor_id' => $actor->id,
            ]);
            CommitmentEvent::query()->create([
                'commitment_id' => $locked->id,
                'actor_id' => $actor->id,
                'event_type' => CommitmentEventType::PlannerBound,
                'payload' => ['binding_uuid' => $binding->uuid, 'plan_uuid' => $plan->uuid, 'existing_plan' => true],
            ]);

            return $binding;
        }, 3);
    }

    private function context(Commitment $commitment): ?Context
    {
        $commitment->loadMissing('contractVersion.contract.contextBinding.context');

        return $commitment->contractVersion?->contract?->contextBinding?->context;
    }
}
