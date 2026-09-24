<?php

namespace App\Actions\Proposals;

use App\Models\Actor;
use App\Models\Proposal;
use App\Models\ProposalEvent;
use App\Models\User;
use App\ProposalEventType;
use App\ProposalStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CancelProposal
{
    public function execute(Proposal $proposal, User $user): Proposal
    {
        $current = $this->currentUser($user);
        Gate::forUser($current)->authorize('cancel', $proposal);

        return DB::transaction(function () use ($proposal, $current): Proposal {
            $locked = Proposal::query()->lockForUpdate()->findOrFail($proposal->id);
            abort_unless($locked->status === ProposalStatus::Negotiating, 422);

            $locked->applyStatus(ProposalStatus::Cancelled);

            ProposalEvent::query()->create([
                'proposal_id' => $locked->id,
                'proposal_version_id' => $locked->currentVersionRecord()?->id,
                'actor_id' => $current->actor->id,
                'event_type' => ProposalEventType::Cancelled,
                'payload' => null,
            ]);

            return $locked->fresh(['parties.actor.user', 'contextBinding.context', 'events']);
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
