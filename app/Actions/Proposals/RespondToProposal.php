<?php

namespace App\Actions\Proposals;

use App\Actions\Contracts\CreateContractFromProposal;
use App\Models\Actor;
use App\Models\Proposal;
use App\Models\ProposalDecision;
use App\Models\ProposalEvent;
use App\Models\ProposalParty;
use App\Models\ProposalVersion;
use App\Models\User;
use App\ProposalDecisionKind;
use App\ProposalEventType;
use App\ProposalStatus;
use App\Support\Deals\DealParties;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RespondToProposal
{
    public function __construct(private readonly CreateContractFromProposal $agreements) {}

    public function execute(
        Proposal $proposal,
        User $user,
        ProposalDecisionKind $decision,
        ?string $note = null,
        ?int $expectedVersion = null,
    ): ProposalDecision {
        $current = $this->currentUser($user);
        Gate::forUser($current)->authorize('respond', $proposal);

        $note = trim((string) $note);
        abort_if(mb_strlen($note) > 2000, 422, 'Proposal response note may not exceed 2000 characters.');

        return DB::transaction(function () use ($proposal, $current, $decision, $note, $expectedVersion): ProposalDecision {
            $locked = Proposal::query()->lockForUpdate()->findOrFail($proposal->id);
            // Authority is re-read here, before any state-dependent answer, so a revoked representative learns nothing.
            $party = DealParties::sole(
                ProposalParty::query()->where('proposal_id', $locked->id)->lockForUpdate()->get(),
                $current,
            );

            abort_unless($locked->status === ProposalStatus::Negotiating, 422, 'This Proposal is no longer open for responses.');

            $version = ProposalVersion::query()
                ->where('proposal_id', $locked->id)
                ->latest('version')
                ->lockForUpdate()
                ->firstOrFail();

            // The decision binds the exact version the person reviewed. Structured-Terms versions require it;
            // only pre-M3 free-text versions may still be answered without one.
            abort_if($expectedVersion === null && $version->terms_data !== null, 422, 'The reviewed Terms version is required.');
            abort_if(
                $expectedVersion !== null && (int) $version->version !== $expectedVersion,
                409,
                'The Terms changed since you reviewed them. Review the new version first.',
            );

            abort_if(
                ProposalDecision::query()
                    ->where('proposal_version_id', $version->id)
                    ->where('proposal_party_id', $party->id)
                    ->exists(),
                422,
                'This party has already responded to the current Proposal version.',
            );

            $record = ProposalDecision::query()->create([
                'proposal_version_id' => $version->id,
                'proposal_party_id' => $party->id,
                'decision' => $decision,
                'note' => $note !== '' ? $note : null,
                'decided_by_user_id' => $current->id,
                'decided_at' => now(),
            ]);

            ProposalEvent::query()->create([
                'proposal_id' => $locked->id,
                'proposal_version_id' => $version->id,
                'actor_id' => $current->actor->id,
                'event_type' => match ($decision) {
                    ProposalDecisionKind::Accepted => ProposalEventType::Accepted,
                    ProposalDecisionKind::Rejected => ProposalEventType::Rejected,
                    ProposalDecisionKind::ChangesRequested => ProposalEventType::ChangesRequested,
                },
                'payload' => ['note' => $record->note],
            ]);

            if ($decision === ProposalDecisionKind::Rejected) {
                $locked->applyStatus(ProposalStatus::Rejected);
            } elseif ($decision === ProposalDecisionKind::Accepted && $this->allRequiredAccepted($locked, $version)) {
                $locked->applyStatus(ProposalStatus::Accepted);
                $this->agree($locked, $version, $current);
            }

            return $record->fresh([
                'proposalVersion.proposal',
                'party.actor.user',
                'decidedByUser',
            ]);
        }, attempts: 3);
    }

    /**
     * Structured Terms carry their own effective date, so the last required acceptance completes the Agreement:
     * Contract and derived acceptances are written in this same transaction. Free-text Proposals keep the explicit Contract form.
     */
    private function agree(Proposal $proposal, ProposalVersion $version, User $current): void
    {
        if ($version->terms_data === null) {
            return;
        }

        $effective = $version->terms_data['effective'] ?? ['mode' => 'on_acceptance'];
        $now = CarbonImmutable::now();
        $at = ($effective['mode'] ?? 'on_acceptance') === 'at' ? CarbonImmutable::parse($effective['at']) : $now;

        $this->agreements->execute(
            $proposal,
            $current,
            $at->lt($now) ? $now : $at,
            $effective['timezone'] ?? 'UTC',
            deriveAcceptances: true,
        );
    }

    private function allRequiredAccepted(Proposal $proposal, ProposalVersion $version): bool
    {
        $requiredPartyIds = $proposal->parties()
            ->where('required', true)
            ->pluck('id');

        $acceptedPartyIds = ProposalDecision::query()
            ->where('proposal_version_id', $version->id)
            ->where('decision', ProposalDecisionKind::Accepted->value)
            ->whereIn('proposal_party_id', $requiredPartyIds)
            ->pluck('proposal_party_id');

        return $requiredPartyIds->isNotEmpty()
            && $acceptedPartyIds->unique()->count() === $requiredPartyIds->count();
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
