<?php

namespace App\Actions\Contracts;

use App\ContextKind;
use App\ContractEventType;
use App\ContractStatus;
use App\Models\Actor;
use App\Models\Context;
use App\Models\Contract;
use App\Models\ContractContext;
use App\Models\ContractEvent;
use App\Models\Proposal;
use App\Models\ProposalParty;
use App\Models\ProposalVersion;
use App\Models\User;
use App\ProposalStatus;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CreateContractFromProposal
{
    public function __construct(private readonly CreateContractVersion $versions) {}

    public function execute(
        Proposal $proposal,
        User $user,
        CarbonInterface $effectiveFrom,
        string $effectiveTimezone,
    ): Contract {
        $current = $this->currentUser($user);

        Gate::forUser($current)->authorize('create', Contract::class);
        Gate::forUser($current)->authorize('view', $proposal);

        return DB::transaction(function () use (
            $proposal,
            $current,
            $effectiveFrom,
            $effectiveTimezone,
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
                    'role' => $party->role,
                    'required' => $party->required,
                    'source_proposal_party' => $party,
                ])
                ->values()
                ->all();

            $this->versions->execute(
                $contract,
                $current,
                $sourceVersion->termsRevision,
                $partySpecs,
                $effectiveFrom,
                $effectiveTimezone,
                'Created explicitly from accepted ProposalVersion '.$sourceVersion->version,
            );

            return $contract->fresh([
                'relationship',
                'sourceProposalVersion.termsRevision.content',
                'creator.user',
                'contextBinding.context',
                'versions.termsRevision.content',
                'versions.parties.actor.user',
                'versions.parties.acceptance',
                'events.actor.user',
            ]);
        }, attempts: 3);
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
