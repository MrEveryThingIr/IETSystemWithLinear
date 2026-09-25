<?php

namespace App\Actions\Fulfillments;

use App\CommitmentEventType;
use App\FulfillmentStatus;
use App\Models\Actor;
use App\Models\CommitmentEvent;
use App\Models\Fulfillment;
use App\Models\FulfillmentDispute;
use App\Models\User;
use App\Support\CommitmentProgress;
use App\Support\QuantityAmount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ResolveFulfillmentDispute
{
    public function __construct(private readonly CommitmentProgress $progress) {}

    public function execute(
        FulfillmentDispute $dispute,
        User $user,
        FulfillmentStatus $resolution,
        ?string $note = null,
    ): FulfillmentDispute {
        abort_unless(in_array($resolution, [FulfillmentStatus::Accepted, FulfillmentStatus::Rejected], true), 422, 'Dispute resolution must be accepted or rejected.');

        $current = $this->currentUser($user);
        Gate::forUser($current)->authorize('resolve', $dispute);

        $note = trim((string) $note);
        abort_if(mb_strlen($note) > 5000, 422, 'Dispute resolution note may not exceed 5000 characters.');

        return DB::transaction(function () use ($dispute, $current, $resolution, $note): FulfillmentDispute {
            $locked = FulfillmentDispute::query()
                ->with('fulfillment.commitment')
                ->lockForUpdate()
                ->findOrFail($dispute->id);

            Gate::forUser($current)->authorize('resolve', $locked);

            $fulfillment = Fulfillment::query()
                ->with('commitment')
                ->lockForUpdate()
                ->findOrFail($locked->fulfillment_id);

            if ($resolution === FulfillmentStatus::Accepted) {
                $accepted = $this->progress->acceptedQuantity($fulfillment->commitment);

                abort_if(
                    QuantityAmount::compare(
                        QuantityAmount::add($accepted, $fulfillment->quantity),
                        $fulfillment->commitment->quantity,
                    ) > 0,
                    422,
                    'Resolving this Fulfillment as accepted would exceed the Commitment quantity.',
                );
            }

            $actor = Actor::query()->findOrFail($current->actor->id);
            $resolvedAt = now();

            $locked->resolve(
                $actor,
                $resolution,
                $note !== '' ? $note : null,
                $resolvedAt,
            );
            $fulfillment->transition($resolution);

            CommitmentEvent::query()->create([
                'commitment_id' => $fulfillment->commitment_id,
                'actor_id' => $actor->id,
                'event_type' => CommitmentEventType::DisputeResolved,
                'payload' => [
                    'fulfillment_uuid' => $fulfillment->uuid,
                    'dispute_uuid' => $locked->uuid,
                    'resolution' => $resolution->value,
                    'note' => $note !== '' ? $note : null,
                ],
            ]);

            return $locked->fresh([
                'fulfillment.commitment',
                'openedBy.user',
                'resolvedBy.user',
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
