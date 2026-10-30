<?php

namespace App\Http\Controllers\Deals;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\IntentVersion;
use App\Models\Relationship;
use App\ProposalStatus;
use App\Support\Deals\AgreementDocument;
use App\Support\Deals\AgreementTerms;
use App\Support\Deals\DealPrincipals;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * A clean printable copy of the EXECUTED Agreement of a Deal: parties, numbered terms, the version that was accepted, who
 * accepted it and when. A draft or an unaccepted proposal is never rendered as an executed agreement.
 */
class AgreementPrintController extends Controller
{
    public function __invoke(Request $request, Relationship $relationship): View
    {
        $contract = Contract::query()
            ->where('relationship_id', $relationship->id)
            ->whereHas('sourceProposalVersion.proposal', fn ($q) => $q->where('status', ProposalStatus::Accepted->value))
            ->with([
                'sourceProposalVersion.proposal.versions.proposedBy.user',
                'sourceProposalVersion.termsRevision',
                'versions.parties.business', 'versions.parties.group', 'versions.parties.actor.user',
                'versions.parties.acceptance.acceptedByUser', 'versions.parties.acceptance.sourceDecision',
            ])
            ->latest('id')
            ->first();

        abort_unless($contract instanceof Contract, 404, 'This Deal has no executed Agreement yet.');

        $source = $contract->sourceProposalVersion;
        $version = $contract->activeVersionRecord() ?? $contract->versions->sortBy('version')->first();
        $version->loadMissing(['contract.sourceProposalVersion', 'parties.business', 'parties.group', 'parties.actor.user', 'parties.acceptance.acceptedByUser', 'termsRevision']);
        $terms = AgreementTerms::of($version);
        $origin = $relationship->originating_intent_version_id ? IntentVersion::query()->find($relationship->originating_intent_version_id) : null;

        return view('deals.agreement-print', [
            'contract' => $contract,
            'version' => $version,
            'source' => $source,
            'terms' => $terms,
            'sections' => $terms !== null ? AgreementDocument::sections($terms, DealPrincipals::labels($relationship)) : [],
            'legacyText' => $terms === null ? (string) ($version->termsRevision->payload['terms'] ?? '') : null,
            'agreementVersions' => $contract->versions->sortBy('version'),
            'parties' => $version->parties,
            'versions' => $source->proposal->versions->sortBy('version'),
            'originTitle' => $origin?->snapshot['core']['title'] ?? null,
            'originVersion' => $origin?->version,
            'relationship' => $relationship,
        ]);
    }
}
