<?php

namespace App\Support\Deals;

use App\Models\Contract;
use App\Models\Proposal;
use App\Models\Relationship;

/** The one place that knows where a Proposal or Contract lives for a person: the Deal workspace. */
final class DealLinks
{
    public static function workspace(Relationship $deal, string $tab = 'conversation', ?int $version = null): string
    {
        return route('relationships.show', array_filter(['relationship' => $deal, 'tab' => $tab, 'v' => $version], fn ($v) => $v !== null));
    }

    /** A Proposal opens the Agreement tab at its version; a standalone legacy Proposal keeps its own page. */
    public static function proposal(Proposal $proposal, ?int $version = null): string
    {
        $deal = $proposal->relationship;

        return $deal instanceof Relationship
            ? self::workspace($deal, 'agreement', $version ?? $proposal->currentVersionRecord()?->version)
            : route('proposals.show', $proposal);
    }

    /** A Contract is the legal record of an accepted Proposal; from a Deal it is reached through the Agreement tab. */
    public static function contract(Contract $contract): string
    {
        $deal = $contract->relationship;

        return $deal instanceof Relationship
            ? self::workspace($deal, 'agreement', $contract->sourceProposalVersion?->version)
            : route('contracts.show', $contract);
    }
}
