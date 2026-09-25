<?php

namespace App\Actions\Contracts;

use App\Models\Contract;
use App\Models\ContractVersion;
use App\Models\ContractVersionParty;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Gate;

class ProposeContractAmendment
{
    public function __construct(
        private readonly PublishContractTerms $terms,
        private readonly CreateContractVersion $versions,
    ) {}

    public function execute(
        Contract $contract,
        User $user,
        string $title,
        string $terms,
        CarbonInterface $effectiveFrom,
        string $effectiveTimezone,
        ?string $summary = null,
        ?string $notes = null,
        ?string $versionNote = null,
    ): ContractVersion {
        Gate::forUser($user)->authorize('amend', $contract);

        $contract->loadMissing('contextBinding.context');
        $context = $contract->contextBinding?->context;
        abort_unless($context !== null, 500, 'Contract Context is missing.');

        $active = $contract->activeVersionRecord();
        abort_unless($active instanceof ContractVersion, 422, 'Active ContractVersion is missing.');

        $active->loadMissing(['parties.actor', 'parties.sourceProposalParty']);

        $revision = $this->terms->execute(
            $context,
            $user,
            $title,
            $terms,
            $summary,
            $notes,
        );

        $partySpecs = $active->parties
            ->map(fn (ContractVersionParty $party): array => [
                'actor' => $party->actor,
                'role' => $party->role,
                'required' => $party->required,
                'source_proposal_party' => $party->sourceProposalParty,
            ])
            ->values()
            ->all();

        return $this->versions->execute(
            $contract,
            $user,
            $revision,
            $partySpecs,
            $effectiveFrom,
            $effectiveTimezone,
            $versionNote,
        );
    }
}
