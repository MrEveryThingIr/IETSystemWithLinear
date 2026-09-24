<?php

namespace App\Policies;

use App\Models\Actor;
use App\Models\Proposal;
use App\Models\ProposalDecision;
use App\Models\ProposalParty;
use App\Models\ProposalVersion;
use App\Models\User;
use App\ProposalStatus;

class ProposalPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->currentActor($user) instanceof Actor;
    }

    public function create(User $user): bool
    {
        return $this->currentActor($user) instanceof Actor;
    }

    public function view(User $user, Proposal $proposal): bool
    {
        return $this->party($user, $proposal) instanceof ProposalParty;
    }

    public function participate(User $user, Proposal $proposal): bool
    {
        $current = Proposal::query()->find($proposal->id);

        return $current instanceof Proposal
            && $current->status === ProposalStatus::Negotiating
            && $this->party($user, $current) instanceof ProposalParty;
    }

    public function respond(User $user, Proposal $proposal): bool
    {
        if (! $this->participate($user, $proposal)) {
            return false;
        }

        $party = $this->party($user, $proposal);
        $version = $proposal->currentVersionRecord();

        return $party instanceof ProposalParty
            && $version instanceof ProposalVersion
            && ! ProposalDecision::query()
                ->where('proposal_version_id', $version->id)
                ->where('proposal_party_id', $party->id)
                ->exists();
    }

    public function cancel(User $user, Proposal $proposal): bool
    {
        $actor = $this->currentActor($user);
        $current = Proposal::query()->find($proposal->id);

        return $actor instanceof Actor
            && $current instanceof Proposal
            && $current->status === ProposalStatus::Negotiating
            && (int) $current->created_by_actor_id === (int) $actor->id;
    }

    private function party(User $user, Proposal $proposal): ?ProposalParty
    {
        $actor = $this->currentActor($user);

        if (! $actor instanceof Actor) {
            return null;
        }

        return ProposalParty::query()
            ->where('proposal_id', $proposal->id)
            ->where('actor_id', $actor->id)
            ->first();
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
