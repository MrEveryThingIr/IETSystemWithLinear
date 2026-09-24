<?php

namespace App\Actions\Contracts;

use App\ContractEventType;
use App\ContractStatus;
use App\ContractVersionStatus;
use App\Models\Actor;
use App\Models\Contract;
use App\Models\ContractAcceptance;
use App\Models\ContractEvent;
use App\Models\ContractVersion;
use App\Models\ContractVersionParty;
use App\Models\ProposalParty;
use App\Models\SpaceContentRevision;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class CreateContractVersion
{
    public function __construct(private readonly FinalizeContractVersionAcceptance $finalize) {}

    /**
     * @param  list<array{actor: Actor, role: string, required?: bool, source_proposal_party?: ?ProposalParty}>  $parties
     */
    public function execute(
        Contract $contract,
        User $user,
        SpaceContentRevision $termsRevision,
        array $parties,
        CarbonInterface $effectiveFrom,
        string $effectiveTimezone,
        ?string $note = null,
    ): ContractVersion {
        $current = $this->currentUser($user);
        Gate::forUser($current)->authorize('participate', $contract);

        abort_unless($termsRevision->hasVerifiableManifest(), 422, 'Contract terms must be a sealed published Content revision.');

        $effectiveTimezone = trim($effectiveTimezone);
        abort_unless(
            in_array($effectiveTimezone, timezone_identifiers_list(), true),
            422,
            'Contract effective timezone is invalid.',
        );

        $effectiveAt = CarbonImmutable::instance($effectiveFrom)->utc();
        abort_if(
            $effectiveAt->lt(CarbonImmutable::now()->subMinute()),
            422,
            'Contract effective time cannot be historical.',
        );

        $note = trim((string) $note);
        abort_if(mb_strlen($note) > 1000, 422, 'ContractVersion note may not exceed 1000 characters.');

        $version = DB::transaction(function () use (
            $contract,
            $current,
            $termsRevision,
            $parties,
            $effectiveAt,
            $effectiveTimezone,
            $note,
        ): ContractVersion {
            $locked = Contract::query()
                ->with(['contextBinding.context', 'sourceProposalVersion.proposal'])
                ->lockForUpdate()
                ->findOrFail($contract->id);

            $context = $locked->contextBinding?->context;
            abort_unless($context !== null, 500, 'Contract Context is missing.');

            $existingVersions = ContractVersion::query()
                ->where('contract_id', $locked->id)
                ->lockForUpdate()
                ->get();

            $supersedes = null;

            if ($existingVersions->isEmpty()) {
                abort_unless($locked->status === ContractStatus::Pending, 422, 'Initial ContractVersion requires a pending Contract.');

                $sourceProposalVersion = $locked->sourceProposalVersion;

                if ($sourceProposalVersion !== null) {
                    abort_unless(
                        (int) $sourceProposalVersion->terms_content_revision_id === (int) $termsRevision->id,
                        422,
                        'Initial Contract terms must match the accepted source ProposalVersion exactly.',
                    );
                } else {
                    abort_unless(
                        (int) $termsRevision->content->context_id === (int) $context->id,
                        422,
                        'Direct Contract terms must belong to the Contract Context.',
                    );
                }
            } else {
                abort_unless($locked->status === ContractStatus::Active, 422, 'Only an active Contract may receive an amendment.');

                abort_if(
                    $existingVersions->contains(fn (ContractVersion $candidate): bool => in_array(
                        $candidate->status,
                        [ContractVersionStatus::Proposed, ContractVersionStatus::Accepted],
                        true,
                    )),
                    422,
                    'This Contract already has a pending amendment.',
                );

                $supersedes = $existingVersions
                    ->first(fn (ContractVersion $candidate): bool => $candidate->status === ContractVersionStatus::Active);

                abort_unless($supersedes instanceof ContractVersion, 422, 'Active ContractVersion is missing.');
                abort_unless($effectiveAt->isFuture(), 422, 'An amendment must take effect in the future.');
                abort_unless(
                    (int) $termsRevision->content->context_id === (int) $context->id,
                    422,
                    'Amendment terms must belong to the Contract Context.',
                );
            }

            $normalizedParties = $this->normalizeParties($locked, $parties, $current->actor);
            $nextVersion = ((int) $existingVersions->max('version')) + 1;

            $version = ContractVersion::query()->create([
                'contract_id' => $locked->id,
                'version' => $nextVersion,
                'terms_content_revision_id' => $termsRevision->id,
                'supersedes_version_id' => $supersedes?->id,
                'proposed_by_actor_id' => $current->actor->id,
                'status' => ContractVersionStatus::Proposed,
                'effective_from' => $effectiveAt,
                'effective_timezone' => $effectiveTimezone,
                'note' => $note !== '' ? $note : null,
                'proposed_at' => now(),
            ]);

            $proposerParty = null;

            foreach ($normalizedParties as $spec) {
                $party = ContractVersionParty::query()->create([
                    'contract_version_id' => $version->id,
                    'actor_id' => $spec['actor']->id,
                    'role' => $spec['role'],
                    'required' => $spec['required'],
                    'source_proposal_party_id' => $spec['source_proposal_party']?->id,
                ]);

                if ((int) $spec['actor']->id === (int) $current->actor->id) {
                    $proposerParty = $party;
                }
            }

            abort_unless($proposerParty instanceof ContractVersionParty, 422, 'ContractVersion proposer must be a party.');

            $acceptance = ContractAcceptance::query()->create([
                'contract_version_party_id' => $proposerParty->id,
                'accepted_by_user_id' => $current->id,
                'accepted_at' => now(),
            ]);

            ContractEvent::query()->create([
                'contract_id' => $locked->id,
                'contract_version_id' => $version->id,
                'actor_id' => $current->actor->id,
                'event_type' => ContractEventType::VersionProposed,
                'payload' => [
                    'version' => $version->version,
                    'terms_content_revision_uuid' => $termsRevision->uuid,
                    'effective_from' => $effectiveAt->toISOString(),
                    'effective_timezone' => $effectiveTimezone,
                ],
            ]);

            ContractEvent::query()->create([
                'contract_id' => $locked->id,
                'contract_version_id' => $version->id,
                'actor_id' => $current->actor->id,
                'event_type' => ContractEventType::PartyAccepted,
                'payload' => [
                    'contract_version_party_uuid' => $proposerParty->uuid,
                    'acceptance_uuid' => $acceptance->uuid,
                ],
            ]);

            return $version->fresh([
                'contract',
                'termsRevision.content',
                'parties.actor.user',
                'parties.acceptance',
            ]);
        }, attempts: 3);

        return $this->finalize->execute($version, $current->actor);
    }

    /**
     * @param  list<array{actor: Actor, role: string, required?: bool, source_proposal_party?: ?ProposalParty}>  $parties
     * @return list<array{actor: Actor, role: string, required: bool, source_proposal_party: ?ProposalParty}>
     */
    private function normalizeParties(Contract $contract, array $parties, Actor $proposer): array
    {
        abort_if($parties === [] || count($parties) > 20, 422, 'A ContractVersion requires between one and twenty parties.');

        $normalized = [];
        $seen = [];
        $hasRequired = false;
        $hasProposer = false;

        foreach ($parties as $spec) {
            $party = Actor::query()->with('user')->lockForUpdate()->findOrFail($spec['actor']->id);

            abort_if(isset($seen[$party->id]), 422, 'A ContractVersion cannot contain the same Actor twice.');
            abort_unless(
                $party->status === 'active'
                && $party->user instanceof User
                && $party->user->status === 'active'
                && $party->user->email_verified_at !== null,
                422,
                'Contract parties currently require active verified user-backed Actors.',
            );

            $role = Str::squish($spec['role']);
            abort_if($role === '' || mb_strlen($role) > 80, 422, 'Contract party role must be between 1 and 80 characters.');

            $required = (bool) ($spec['required'] ?? true);
            $sourceProposalParty = $spec['source_proposal_party'] ?? null;

            if ($sourceProposalParty instanceof ProposalParty) {
                abort_unless(
                    $contract->sourceProposalVersion !== null
                    && (int) $sourceProposalParty->proposal_id === (int) $contract->sourceProposalVersion->proposal_id
                    && (int) $sourceProposalParty->actor_id === (int) $party->id,
                    422,
                    'Contract party Proposal provenance is invalid.',
                );
            }

            $seen[$party->id] = true;
            $hasRequired = $hasRequired || $required;
            $hasProposer = $hasProposer || (int) $party->id === (int) $proposer->id;

            $normalized[] = [
                'actor' => $party,
                'role' => $role,
                'required' => $required,
                'source_proposal_party' => $sourceProposalParty,
            ];
        }

        abort_unless($hasRequired, 422, 'A ContractVersion requires at least one required party.');
        abort_unless($hasProposer, 422, 'ContractVersion proposer must be included as a party.');

        return $normalized;
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
