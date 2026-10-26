<?php

namespace App\Support\Deals;

use App\Models\ContractVersion;

/** The typed Terms an Agreement version stands on: its own (an amendment), or those of the ProposalVersion it was accepted from. */
final class AgreementTerms
{
    /** @return array<string, mixed>|null null for agreements written as free text before structured Terms */
    public static function of(ContractVersion $version): ?array
    {
        if ($version->terms_data !== null) {
            return $version->terms_data;
        }

        return $version->supersedes_version_id === null ? $version->contract->sourceProposalVersion?->terms_data : null;
    }
}
