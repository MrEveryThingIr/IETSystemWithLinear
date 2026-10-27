<?php

namespace App\Policies;

use App\ContractStatus;
use App\Models\Actor;
use App\Models\Commitment;
use App\Models\Contract;
use App\Models\User;
use App\Support\Deals\DealParties;
use App\Support\RequestMemo;
use Illuminate\Support\Facades\Gate;

class CommitmentPolicy
{
    public function view(User $user, Commitment $commitment): bool
    {
        $commitment->loadMissing('contractVersion.contract');

        return Gate::forUser($user)->allows('view', $commitment->contractVersion->contract);
    }

    public function create(User $user, Contract $contract): bool
    {
        $actor = $this->currentActor($user);
        $current = Contract::query()->find($contract->id);

        return $actor instanceof Actor
            && $current instanceof Contract
            && $current->status === ContractStatus::Active
            && (int) $current->created_by_actor_id === (int) $actor->id
            && Gate::forUser($user)->allows('view', $current);
    }

    /** A Deal's Work step: a person who represents one of the active Agreement's parties may set up its duties (the action checks who confirms what). */
    public function createAsParty(User $user, Contract $contract): bool
    {
        $current = Contract::query()->find($contract->id);

        return $this->currentActor($user) instanceof Actor
            && $current instanceof Contract
            && $current->status === ContractStatus::Active
            && $this->representsParty($user, $current);
    }

    public function manageAsParty(User $user, Commitment $commitment): bool
    {
        $commitment->loadMissing('contractVersion.contract');

        return $this->currentActor($user) instanceof Actor && $this->representsParty($user, $commitment->contractVersion->contract);
    }

    private function representsParty(User $user, Contract $contract): bool
    {
        $version = $contract->activeVersionRecord();

        return $version !== null && DealParties::representedBy($version->parties, $user)->isNotEmpty();
    }

    public function manage(User $user, Commitment $commitment): bool
    {
        $actor = $this->currentActor($user);

        return $actor instanceof Actor
            && (int) $commitment->created_by_actor_id === (int) $actor->id
            && $this->view($user, $commitment);
    }

    public function submit(User $user, Commitment $commitment): bool
    {
        $actor = $this->currentActor($user);

        return $actor instanceof Actor
            && (int) $commitment->obligor_actor_id === (int) $actor->id
            && $this->view($user, $commitment);
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
