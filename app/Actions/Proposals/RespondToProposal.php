<?php

namespace App\Actions\Proposals;

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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RespondToProposal
{
    public function execute(
        Proposal $proposal,
        User $user,
        ProposalDecisionKind $decision,
        ?string $note = null,
    ): ProposalDecision {
        $current = $this->currentUser($user);
        Gate::forUser($current)->authorize('respond', $proposal);

        $note = trim((string) $note);
        abort_if(mb_strlen($note) > 2000, 422, 'Proposal response note may not exceed 2000 characters.');

        return DB::transaction(function () use ($proposal, $current, $decision, $note): ProposalDecision {
            $locked = Proposal::query()->lockForUpdate()->findOrFail($proposal->id);
            abort_unless($locked->status === ProposalStatus::Negotiating, 422, 'This Proposal is no longer open for responses.');

            $version = ProposalVersion::query()
                ->where('proposal_id', $locked->id)
                ->latest('version')
                ->lockForUpdate()
                ->firstOrFail();

            $party = ProposalParty::query()
                ->where('proposal_id', $locked->id)
                ->where('actor_id', $current->actor->id)
                ->lockForUpdate()
                ->firstOrFail();

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
            }

            return $record->fresh([
                'proposalVersion.proposal',
                'party.actor.user',
                'decidedByUser',
            ]);
        }, attempts: 3);
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
