<?php

namespace App\Actions\Contracts;

use App\ContextKind;
use App\ContractEventType;
use App\ContractStatus;
use App\Models\Actor;
use App\Models\Context;
use App\Models\Contract;
use App\Models\ContractAcceptance;
use App\Models\ContractContext;
use App\Models\ContractEvent;
use App\Models\ContractVersion;
use App\Models\Proposal;
use App\Models\ProposalDecision;
use App\Models\ProposalParty;
use App\Models\ProposalVersion;
use App\Models\User;
use App\ProposalDecisionKind;
use App\ProposalStatus;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CreateContractFromProposal
{
    public function __construct(
        private readonly CreateContractVersion $versions,
        private readonly ConfigureContractServiceTerms $serviceTerms,
        private readonly AcceptContractVersion $acceptVersions,
        private readonly FinalizeContractVersionAcceptance $finalize,
    ) {}

    public function execute(
        Proposal $proposal,
        User $user,
        CarbonInterface $effectiveFrom,
        string $effectiveTimezone,
        ?array $serviceTerms = null,
        bool $deriveAcceptances = false,
    ): Contract {
        $current = $this->currentUser($user);

        Gate::forUser($current)->authorize('create', Contract::class);
        Gate::forUser($current)->authorize('view', $proposal);

        return DB::transaction(function () use (
            $proposal,
            $current,
            $effectiveFrom,
            $effectiveTimezone,
            $serviceTerms,
            $deriveAcceptances,
        ): Contract {
            $lockedProposal = Proposal::query()
                ->with([
                    'relationship',
                    'parties.actor.user',
                ])
                ->lockForUpdate()
                ->findOrFail($proposal->id);

            abort_unless(
                $lockedProposal->status === ProposalStatus::Accepted,
                422,
                'Only an accepted Proposal may become Contract input.',
            );

            $sourceVersion = $lockedProposal->currentVersionRecord();
            abort_unless($sourceVersion instanceof ProposalVersion, 422, 'Accepted ProposalVersion is missing.');
            $sourceVersion->loadMissing('termsRevision.content');

            abort_if(
                Contract::query()
                    ->where('source_proposal_version_id', $sourceVersion->id)
                    ->exists(),
                422,
                'This accepted ProposalVersion already has a Contract.',
            );

            $creator = Actor::query()->with('user')->lockForUpdate()->findOrFail($current->actor->id);

            $contract = Contract::query()->create([
                'title' => $lockedProposal->title,
                'relationship_id' => $lockedProposal->relationship_id,
                'source_proposal_version_id' => $sourceVersion->id,
                'created_by_actor_id' => $creator->id,
                'status' => ContractStatus::Pending,
            ]);

            $context = Context::query()->create(['kind' => ContextKind::Contract]);

            ContractContext::query()->create([
                'context_id' => $context->id,
                'contract_id' => $contract->id,
            ]);

            ContractEvent::query()->create([
                'contract_id' => $contract->id,
                'contract_version_id' => null,
                'actor_id' => $creator->id,
                'event_type' => ContractEventType::Created,
                'payload' => [
                    'relationship_uuid' => $lockedProposal->relationship?->uuid,
                    'source_proposal_version_uuid' => $sourceVersion->uuid,
                ],
            ]);

            $partySpecs = $lockedProposal->parties
                ->map(fn (ProposalParty $party): array => [
                    'actor' => $party->actor,
                    'party' => $party->principal(),
                    'role' => $party->role,
                    'required' => $party->required,
                    'source_proposal_party' => $party,
                ])
                ->values()
                ->all();

            $version = $this->versions->execute(
                $contract,
                $current,
                $sourceVersion->termsRevision,
                $partySpecs,
                $effectiveFrom,
                $effectiveTimezone,
                'Created explicitly from accepted ProposalVersion '.$sourceVersion->version,
                acceptProposer: $serviceTerms === null && ! $deriveAcceptances,
            );

            if ($deriveAcceptances) {
                $this->deriveAcceptances($version, $sourceVersion);
            }

            if ($serviceTerms !== null) {
                $this->serviceTerms->executeFromInput($version, $current, $serviceTerms);
                $this->acceptVersions->execute($version, $current);
            }

            return $contract->fresh([
                'relationship',
                'sourceProposalVersion.termsRevision.content',
                'creator.user',
                'contextBinding.context',
                'versions.termsRevision.content',
                'versions.parties.actor.user',
                'versions.parties.acceptance',
                'versions.serviceTerm.employer.user',
                'versions.serviceTerm.worker.user',
                'versions.serviceTerm.monetaryUnit',
                'events.actor.user',
            ]);
        }, attempts: 3);
    }

    /**
     * Single Agree step: each party's agreement to the exact Terms was already recorded as a ProposalDecision.
     * The ContractVersion carries the very same sealed revision, so its acceptance is derived from that decision
     * (same signer, same time) instead of asking again, then the version is finalized as usual.
     */
    private function deriveAcceptances(ContractVersion $version, ProposalVersion $source): void
    {
        $version->loadMissing('parties');

        foreach ($version->parties as $party) {
            $decision = ProposalDecision::query()
                ->where('proposal_version_id', $source->id)
                ->where('proposal_party_id', $party->source_proposal_party_id)
                ->where('decision', ProposalDecisionKind::Accepted->value)
                ->first();

            if (! $decision instanceof ProposalDecision) {
                continue;
            }

            $acceptance = ContractAcceptance::query()->create([
                'contract_version_party_id' => $party->id,
                'source_proposal_decision_id' => $decision->id,
                'accepted_by_user_id' => $decision->decided_by_user_id,
                'accepted_at' => $decision->decided_at,
            ]);

            ContractEvent::query()->create([
                'contract_id' => $version->contract_id,
                'contract_version_id' => $version->id,
                'actor_id' => $party->actor_id,
                'event_type' => ContractEventType::PartyAccepted,
                'payload' => [
                    'contract_version_party_uuid' => $party->uuid,
                    'acceptance_uuid' => $acceptance->uuid,
                    'source_proposal_decision_uuid' => $decision->uuid,
                ],
            ]);
        }

        $this->finalize->execute($version, null);
    }

    private function currentUser(User $user): User
    {
        $current = User::query()->with('actor')->find($user->id);

        abort_unless(
            $current instanceof User
            && $current->status === 'active'
            && $current->email_verified_at !== null
            && $current->actor instanceof Actor
            && $current->actor->status === 'active',
            403,
        );

        return $current;
    }
}
