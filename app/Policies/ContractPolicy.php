<?php

namespace App\Policies;

use App\ContractStatus;
use App\ContractVersionStatus;
use App\Models\Actor;
use App\Models\Contract;
use App\Models\ContractAcceptance;
use App\Models\ContractVersion;
use App\Models\ContractVersionParty;
use App\Models\User;

class ContractPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->currentActor($user) instanceof Actor;
    }

    public function create(User $user): bool
    {
        return $this->currentActor($user) instanceof Actor;
    }

    public function view(User $user, Contract $contract): bool
    {
        $actor = $this->currentActor($user);

        if (! $actor instanceof Actor) {
            return false;
        }

        if ((int) $contract->created_by_actor_id === (int) $actor->id) {
            return true;
        }

        return ContractVersionParty::query()
            ->whereHas('contractVersion', fn ($query) => $query->where('contract_id', $contract->id))
            ->where('actor_id', $actor->id)
            ->exists();
    }

    public function participate(User $user, Contract $contract): bool
    {
        $actor = $this->currentActor($user);
        $current = Contract::query()->find($contract->id);

        if (! $actor instanceof Actor || ! $current instanceof Contract) {
            return false;
        }

        if ($current->status === ContractStatus::Pending
            && ! $current->versions()->exists()) {
            return (int) $current->created_by_actor_id === (int) $actor->id;
        }

        return ContractVersionParty::query()
            ->whereHas('contractVersion', function ($query) use ($current): void {
                $query->where('contract_id', $current->id)
                    ->whereIn('status', [
                        ContractVersionStatus::Proposed->value,
                        ContractVersionStatus::Accepted->value,
                        ContractVersionStatus::Active->value,
                    ]);
            })
            ->where('actor_id', $actor->id)
            ->exists();
    }

    public function accept(User $user, Contract $contract, ContractVersion $version): bool
    {
        $actor = $this->currentActor($user);
        $current = ContractVersion::query()->find($version->id);

        if (! $actor instanceof Actor
            || ! $current instanceof ContractVersion
            || (int) $current->contract_id !== (int) $contract->id
            || $current->status !== ContractVersionStatus::Proposed) {
            return false;
        }

        $party = ContractVersionParty::query()
            ->where('contract_version_id', $current->id)
            ->where('actor_id', $actor->id)
            ->first();

        return $party instanceof ContractVersionParty
            && ! ContractAcceptance::query()
                ->where('contract_version_party_id', $party->id)
                ->exists();
    }

    public function amend(User $user, Contract $contract): bool
    {
        $actor = $this->currentActor($user);
        $current = Contract::query()->find($contract->id);

        if (! $actor instanceof Actor
            || ! $current instanceof Contract
            || $current->status !== ContractStatus::Active
            || $current->pendingVersionRecord() instanceof ContractVersion) {
            return false;
        }

        $active = $current->activeVersionRecord();

        return $active instanceof ContractVersion
            && ContractVersionParty::query()
                ->where('contract_version_id', $active->id)
                ->where('actor_id', $actor->id)
                ->exists();
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
