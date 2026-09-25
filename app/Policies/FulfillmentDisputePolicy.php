<?php

namespace App\Policies;

use App\FulfillmentDisputeStatus;
use App\Models\Actor;
use App\Models\FulfillmentDispute;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class FulfillmentDisputePolicy
{
    public function view(User $user, FulfillmentDispute $dispute): bool
    {
        $dispute->loadMissing('fulfillment');

        return Gate::forUser($user)->allows('view', $dispute->fulfillment);
    }

    public function resolve(User $user, FulfillmentDispute $dispute): bool
    {
        $actor = $this->currentActor($user);
        $dispute->loadMissing('fulfillment.commitment');

        if (! $actor instanceof Actor
            || $dispute->status !== FulfillmentDisputeStatus::Open
            || (int) $dispute->opened_by_actor_id === (int) $actor->id
            || ! $this->view($user, $dispute)) {
            return false;
        }

        return in_array((int) $actor->id, [
            (int) $dispute->fulfillment->commitment->obligor_actor_id,
            (int) $dispute->fulfillment->commitment->beneficiary_actor_id,
        ], true);
    }

    private function currentActor(User $user): ?Actor
    {
        $current = User::query()->with('actor')->find($user->id);

        if (! $current instanceof User
            || $current->status !== 'active'
            || $current->email_verified_at === null
            || ! $current->actor instanceof Actor
            || $current->actor->status !== 'active') {
            return null;
        }

        return $current->actor;
    }
}
