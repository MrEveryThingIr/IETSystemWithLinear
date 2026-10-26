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
use App\Support\Deals\DealParties;
use App\Support\RequestMemo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

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

        return $this->represented($user, ContractVersionParty::query()
            ->whereHas('contractVersion', fn ($query) => $query->where('contract_id', $contract->id)))->isNotEmpty();
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

        return $this->represented($user, ContractVersionParty::query()
            ->whereHas('contractVersion', function ($query) use ($current): void {
                $query->where('contract_id', $current->id)
                    ->whereIn('status', [
                        ContractVersionStatus::Proposed->value,
                        ContractVersionStatus::Accepted->value,
                        ContractVersionStatus::Active->value,
                    ]);
            }))->isNotEmpty();
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

        $mine = $this->represented($user, ContractVersionParty::query()->where('contract_version_id', $current->id));
        $party = $mine->count() === 1 ? $mine->first() : null;

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
            && $this->represented($user, ContractVersionParty::query()->where('contract_version_id', $active->id))->isNotEmpty();
    }

    /** @return Collection<int, ContractVersionParty> parties this person may act for now, by principal authority */
    private function represented(User $user, Builder $parties): Collection
    {
        return DealParties::representedBy($parties->get(), RequestMemo::user($user));
    }

    private function currentActor(User $user): ?Actor
    {
        $current = RequestMemo::user($user);

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
