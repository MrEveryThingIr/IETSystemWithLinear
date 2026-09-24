<?php

namespace App\Policies;

use App\Models\Actor;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class PlanPolicy
{
    public function view(User $user, Plan $plan): bool
    {
        $plan->loadMissing('context');

        return Gate::forUser($user)->allows('view', $plan->context);
    }

    public function manage(User $user, Plan $plan): bool
    {
        $current = $this->currentActor($user);

        if (! $current instanceof Actor) {
            return false;
        }

        if ((int) $plan->created_by_actor_id === (int) $current->id) {
            return $this->view($user, $plan);
        }

        $plan->loadMissing('context');

        return Gate::forUser($user)->allows('manageContent', $plan->context);
    }

    public function participate(User $user, Plan $plan): bool
    {
        $current = $this->currentActor($user);

        if (! $current instanceof Actor || ! $this->view($user, $plan)) {
            return false;
        }

        if ($this->manage($user, $plan)) {
            return true;
        }

        return $plan->participants()
            ->where('actor_id', $current->id)
            ->where('status', 'active')
            ->exists();
    }

    private function currentActor(User $user): ?Actor
    {
        $current = User::query()->with('actor')->find($user->id);

        if (
            ! $current instanceof User
            || $current->status !== 'active'
            || $current->email_verified_at === null
            || ! $current->actor instanceof Actor
            || $current->actor->status !== 'active'
        ) {
            return null;
        }

        return $current->actor;
    }
}
