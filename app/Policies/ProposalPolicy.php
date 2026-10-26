<?php

namespace App\Policies;

use App\Models\Actor;
use App\Models\ContractVersionParty;
use App\Models\Proposal;
use App\Models\ProposalDecision;
use App\Models\ProposalParty;
use App\Models\ProposalVersion;
use App\Models\User;
use App\ProposalStatus;
use App\Support\Deals\DealParties;
use App\Support\RequestMemo;
use Illuminate\Support\Collection;

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
        return $this->represented($user, $proposal)->isNotEmpty();
    }

    public function participate(User $user, Proposal $proposal): bool
    {
        $current = Proposal::query()->find($proposal->id);

        return $current instanceof Proposal
            && $current->status === ProposalStatus::Negotiating
            && $this->represented($user, $current)->isNotEmpty();
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

        if (! $actor instanceof Actor || ! $current instanceof Proposal || $current->status !== ProposalStatus::Negotiating) {
            return false;
        }

        // The creator, or whoever may currently act for the party the creator proposed as (first party row).
        $creatorParty = ProposalParty::query()->where('proposal_id', $current->id)->orderBy('id')->first();

        return (int) $current->created_by_actor_id === (int) $actor->id
            || ($creatorParty instanceof ProposalParty
                && DealParties::representedBy([$creatorParty], RequestMemo::user($user))->isNotEmpty());
    }

    /** The party this person may act for, by current PartyAuthority on the principal (never by stored actor_id). */
    private function party(User $user, Proposal $proposal): ?ProposalParty
    {
        $parties = $this->represented($user, $proposal);

        return $parties->count() === 1 ? $parties->first() : null;
    }

    /** @return Collection<int, ProposalParty|ContractVersionParty> */
    private function represented(User $user, Proposal $proposal): Collection
    {
        if (! $this->currentActor($user) instanceof Actor) {
            return collect();
        }

        return DealParties::representedBy(ProposalParty::query()->where('proposal_id', $proposal->id)->get(), RequestMemo::user($user));
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
