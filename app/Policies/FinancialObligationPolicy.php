<?php

namespace App\Policies;

use App\Models\Actor;
use App\Models\FinancialObligation;
use App\Models\User;

class FinancialObligationPolicy
{
    public function view(User $user, FinancialObligation $obligation): bool
    {
        $actor = $this->currentActor($user);

        return $actor instanceof Actor
            && in_array((int) $actor->id, [
                (int) $obligation->debtor_actor_id,
                (int) $obligation->creditor_actor_id,
            ], true);
    }

    public function postAccounting(User $user, FinancialObligation $obligation): bool
    {
        return $this->view($user, $obligation)
            && $obligation->isEconomicallyAccepted();
    }

    public function proposeSettlement(User $user, FinancialObligation $obligation): bool
    {
        return $this->view($user, $obligation)
            && $obligation->isEconomicallyAccepted()
            && $obligation->outstandingMinor() > 0;
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
