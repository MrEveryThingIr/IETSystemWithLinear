<?php

namespace App\Support\Deals;

use App\Models\ContractVersionParty;
use App\Models\ProposalParty;
use App\Models\User;
use App\Support\Parties\Party;
use App\Support\Parties\PartyAuthority;
use App\Support\RequestMemo;
use Illuminate\Support\Collection;

/**
 * Who may bind which party of a Proposal or Contract. Authority is always the current
 * PartyAuthority answer for the party's principal; the stored representative (actor_id) is never consulted.
 */
final class DealParties
{
    /**
     * @param  iterable<ProposalParty|ContractVersionParty>  $parties
     * @return Collection<int, ProposalParty|ContractVersionParty>
     */
    public static function representedBy(iterable $parties, User $user, bool $fresh = false): Collection
    {
        if ($fresh) {
            RequestMemo::flush(); // decision time: read membership/management as it is now, not as the page loaded it
        }

        $authority = new PartyAuthority;

        return collect($parties)
            ->filter(fn ($party): bool => $authority->canActFor($user, $party->principal()))
            ->values();
    }

    /**
     * The one party this person signs for, read fresh. Nobody: 403. More than one: they would be
     * consenting for both sides, so it is refused.
     *
     * @param  iterable<ProposalParty|ContractVersionParty>  $parties
     */
    public static function sole(iterable $parties, User $user): ProposalParty|ContractVersionParty
    {
        $mine = self::representedBy($parties, $user, fresh: true);

        abort_if($mine->isEmpty(), 403);
        abort_if($mine->count() > 1, 422, 'You cannot act for more than one party of the same agreement.');

        return $mine->first();
    }

    /**
     * Creation-time guard: none of these people may be able to act for two of these principals.
     *
     * @param  iterable<User>  $users
     * @param  iterable<Party>  $principals
     */
    public static function assertSingleSide(iterable $users, iterable $principals): void
    {
        $authority = new PartyAuthority;

        foreach ($users as $user) {
            $count = collect($principals)->filter(fn (Party $party): bool => $authority->canActFor($user, $party))->count();
            abort_if($count > 1, 422, 'One person cannot represent more than one party of the same agreement.');
        }
    }
}
