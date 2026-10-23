<?php

namespace App\Actions\Planner;

use App\Models\Context;
use App\Models\Plan;
use App\Models\PlanEvent;
use App\Models\User;
use App\PlanEventType;
use App\Support\Planner\LabAuthority;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class ComposePlan
{
    public function execute(User $user, Plan $child, ?Plan $parent): Plan
    {
        $actor = LabAuthority::actor($user);
        Gate::forUser($user)->authorize('manage', $child);
        if ($parent !== null) {
            Gate::forUser($user)->authorize('manage', $parent);
            abort_unless((int) $child->context_id === (int) $parent->context_id, 422, 'A routine stays within one authorized context.');
        }

        return DB::transaction(function () use ($child, $parent, $actor): Plan {
            // Serializes all graph changes in this context, even by different managers.
            Context::query()->lockForUpdate()->findOrFail($child->context_id);
            $locked = Plan::query()->lockForUpdate()->findOrFail($child->id);
            $cursor = $parent?->id;
            $seen = [];
            while ($cursor !== null) {
                abort_if((int) $cursor === (int) $locked->id || isset($seen[$cursor]) || count($seen) >= 1000, 422, 'A plan cannot contain itself, directly or indirectly.');
                $seen[$cursor] = true;
                $cursor = Plan::query()->whereKey($cursor)->value('parent_plan_id');
            }
            if ((int) $locked->parent_plan_id === (int) $parent?->id) {
                return $locked;
            }
            $previous = $locked->parent_plan_id;
            $locked->applyParent($parent?->id);
            PlanEvent::query()->create(['plan_id' => $locked->id, 'actor_id' => $actor->id,
                'event_type' => PlanEventType::StructureChanged,
                'payload' => ['previous_parent_id' => $previous, 'parent_plan_uuid' => $parent?->uuid]]);

            return $locked->fresh();
        }, attempts: 3);
    }
}
