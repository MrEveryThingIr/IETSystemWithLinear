<?php

namespace App\Actions\Deals;

use App\Actions\Contracts\ProposeContractAmendment;
use App\Models\Contract;
use App\Models\ContractVersion;
use App\Models\User;
use App\Support\Deals\AgreementDrafter;
use App\Support\Deals\DealTerms;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Gate;

/**
 * Changing an accepted Agreement: a new ContractVersion that carries typed Terms, effective on a future date and in force only
 * once every party has accepted it (the old version stays in force until then). The ProposalVersion history is untouched.
 */
class AmendAgreement
{
    public function __construct(private readonly ProposeContractAmendment $amend) {}

    /** @param array<string, mixed> $terms schema-1 Terms; the origin of the accepted Terms is carried over */
    public function execute(Contract $contract, User $user, array $terms, CarbonInterface $effectiveFrom, string $timezone, ?string $note = null): ContractVersion
    {
        $contract->loadMissing('relationship.originatingIntent.profile', 'sourceProposalVersion');
        Gate::forUser($user)->authorize('amend', $contract);
        abort_unless($contract->relationship !== null && AgreementDrafter::canDraft($contract->relationship, $user), 403);

        $origin = $contract->sourceProposalVersion?->terms_data['origin'] ?? null;
        if ($origin !== null) {
            $terms['origin'] = $origin;
        }
        $normalized = DealTerms::normalize($terms);

        return $this->amend->execute(
            $contract,
            $user,
            $normalized['title'] ?? $contract->title,
            DealTerms::render($normalized),
            $effectiveFrom,
            $timezone,
            $normalized['summary'] ?? null,
            null,
            $note,
            $normalized,
        );
    }
}
