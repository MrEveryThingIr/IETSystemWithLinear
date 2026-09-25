<?php

namespace App\Policies;

use App\Models\Actor;
use App\Models\Settlement;
use App\Models\User;
use App\SettlementStatus;

class SettlementPolicy
{
    public function view(User $user, Settlement $settlement): bool
    {
        $settlement->loadMissing('obligation');

        return app(FinancialObligationPolicy::class)->view($user, $settlement->obligation);
    }

    public function respond(User $user, Settlement $settlement): bool
    {
        $actor = $this->currentActor($user);
        $settlement->loadMissing('obligation');

        if (! $actor instanceof Actor
            || $settlement->status !== SettlementStatus::PendingConfirmation
            || ! app(FinancialObligationPolicy::class)->view($user, $settlement->obligation)) {
            return false;
        }

        return (int) $actor->id !== (int) $settlement->proposed_by_actor_id;
    }

    public function postAccounting(User $user, Settlement $settlement): bool
    {
        return $settlement->status === SettlementStatus::Confirmed
            && $this->view($user, $settlement);
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
