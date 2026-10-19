<?php

namespace App\Policies\Bookkeeping;

use App\Enums\Bookkeeping\LedgerStatus;
use App\Enums\Bookkeeping\PartyRole;
use App\Enums\Bookkeeping\PartyStatus;
use App\Models\Actor;
use App\Models\Bookkeeping\Ledger;
use App\Models\User;

/**
 * Books are private to their people: a personal book to its owner, a shared
 * or project book to its active parties. Roles narrow what each may do.
 */
class LedgerPolicy
{
    public function view(User $user, Ledger $ledger): bool
    {
        return $this->role($user, $ledger) !== null;
    }

    public function record(User $user, Ledger $ledger): bool
    {
        return $ledger->status === LedgerStatus::Active
            && ($this->role($user, $ledger)?->canRecord() ?? false);
    }

    public function manage(User $user, Ledger $ledger): bool
    {
        return $ledger->status === LedgerStatus::Active
            && ! $ledger->isPersonal()
            && ($this->role($user, $ledger)?->canManage() ?? false);
    }

    public function archive(User $user, Ledger $ledger): bool
    {
        return ! $ledger->isPersonal() && $this->role($user, $ledger) === PartyRole::Owner;
    }

    public function role(User $user, Ledger $ledger): ?PartyRole
    {
        $actor = $this->actor($user);
        if (! $actor instanceof Actor) {
            return null;
        }

        $party = $ledger->parties()
            ->where('actor_id', $actor->id)
            ->where('status', PartyStatus::Active->value)
            ->first();

        return $party?->role;
    }

    private function actor(User $user): ?Actor
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
