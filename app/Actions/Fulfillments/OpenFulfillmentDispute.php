<?php

namespace App\Actions\Fulfillments;

use App\CommitmentEventType;
use App\FulfillmentDisputeStatus;
use App\FulfillmentStatus;
use App\Models\Actor;
use App\Models\CommitmentEvent;
use App\Models\Fulfillment;
use App\Models\FulfillmentDispute;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class OpenFulfillmentDispute
{
    public function execute(Fulfillment $fulfillment, User $user, string $reason): FulfillmentDispute
    {
        $current = $this->currentUser($user);
        Gate::forUser($current)->authorize('openDispute', $fulfillment);

        $reason = trim($reason);
        abort_if($reason === '' || mb_strlen($reason) > 5000, 422, 'Dispute reason must be between 1 and 5000 characters.');

        return DB::transaction(function () use ($fulfillment, $current, $reason): FulfillmentDispute {
            $locked = Fulfillment::query()
                ->with(['commitment', 'dispute'])
                ->lockForUpdate()
                ->findOrFail($fulfillment->id);

            Gate::forUser($current)->authorize('openDispute', $locked);

            $original = $locked->status;
            abort_unless(in_array($original, [FulfillmentStatus::Accepted, FulfillmentStatus::Rejected], true), 422);

            $actor = Actor::query()->findOrFail($current->actor->id);

            $dispute = FulfillmentDispute::query()->create([
                'fulfillment_id' => $locked->id,
                'opened_by_actor_id' => $actor->id,
                'original_status' => $original,
                'reason' => $reason,
                'status' => FulfillmentDisputeStatus::Open,
                'opened_at' => now(),
            ]);

            $locked->transition(FulfillmentStatus::Disputed);

            CommitmentEvent::query()->create([
                'commitment_id' => $locked->commitment_id,
                'actor_id' => $actor->id,
                'event_type' => CommitmentEventType::FulfillmentDisputed,
                'payload' => [
                    'fulfillment_uuid' => $locked->uuid,
                    'dispute_uuid' => $dispute->uuid,
                    'original_status' => $original->value,
                    'reason' => $reason,
                ],
            ]);

            return $dispute->fresh([
                'fulfillment.commitment',
                'openedBy.user',
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
