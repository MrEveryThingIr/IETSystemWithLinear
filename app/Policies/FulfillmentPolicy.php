<?php

namespace App\Policies;

use App\FulfillmentStatus;
use App\Models\Actor;
use App\Models\Fulfillment;
use App\Models\FulfillmentDispute;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class FulfillmentPolicy
{
    public function view(User $user, Fulfillment $fulfillment): bool
    {
        $fulfillment->loadMissing('commitment');

        return Gate::forUser($user)->allows('view', $fulfillment->commitment);
    }

    public function review(User $user, Fulfillment $fulfillment): bool
    {
        $actor = $this->currentActor($user);
        $fulfillment->loadMissing('commitment');

        return $actor instanceof Actor
            && $fulfillment->status === FulfillmentStatus::Submitted
            && (int) $fulfillment->commitment->beneficiary_actor_id === (int) $actor->id
            && $this->view($user, $fulfillment);
    }

    public function correct(User $user, Fulfillment $fulfillment): bool
    {
        $actor = $this->currentActor($user);
        $fulfillment->loadMissing('commitment');

        return $actor instanceof Actor
            && in_array($fulfillment->status, [
                FulfillmentStatus::ClarificationRequested,
                FulfillmentStatus::Rejected,
            ], true)
            && (int) $fulfillment->commitment->obligor_actor_id === (int) $actor->id
            && $this->view($user, $fulfillment);
    }

    public function openDispute(User $user, Fulfillment $fulfillment): bool
    {
        $actor = $this->currentActor($user);
        $fulfillment->loadMissing(['commitment', 'dispute']);

        if (! $actor instanceof Actor
            || $fulfillment->dispute instanceof FulfillmentDispute
            || ! in_array($fulfillment->status, [
                FulfillmentStatus::Accepted,
                FulfillmentStatus::Rejected,
            ], true)
            || ! $this->view($user, $fulfillment)) {
            return false;
        }

        return in_array((int) $actor->id, [
            (int) $fulfillment->commitment->obligor_actor_id,
            (int) $fulfillment->commitment->beneficiary_actor_id,
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
