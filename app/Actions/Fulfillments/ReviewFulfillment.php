<?php

namespace App\Actions\Fulfillments;

use App\CommitmentEventType;
use App\FulfillmentReviewDecision;
use App\FulfillmentStatus;
use App\Models\Actor;
use App\Models\CommitmentEvent;
use App\Models\Fulfillment;
use App\Models\FulfillmentReview;
use App\Models\User;
use App\Support\CommitmentProgress;
use App\Support\QuantityAmount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ReviewFulfillment
{
    public function __construct(private readonly CommitmentProgress $progress) {}

    public function execute(
        Fulfillment $fulfillment,
        User $user,
        FulfillmentReviewDecision $decision,
        ?string $note = null,
    ): FulfillmentReview {
        $current = $this->currentUser($user);
        Gate::forUser($current)->authorize('review', $fulfillment);

        $note = trim((string) $note);
        abort_if(mb_strlen($note) > 5000, 422, 'Fulfillment review note may not exceed 5000 characters.');
        abort_if(
            $decision !== FulfillmentReviewDecision::Accepted && $note === '',
            422,
            'Rejecting or requesting clarification requires a review note.',
        );

        return DB::transaction(function () use ($fulfillment, $current, $decision, $note): FulfillmentReview {
            $locked = Fulfillment::query()
                ->with(['commitment', 'review'])
                ->lockForUpdate()
                ->findOrFail($fulfillment->id);

            Gate::forUser($current)->authorize('review', $locked);
            abort_if($locked->review instanceof FulfillmentReview, 422, 'This Fulfillment already has a review.');

            $status = match ($decision) {
                FulfillmentReviewDecision::Accepted => FulfillmentStatus::Accepted,
                FulfillmentReviewDecision::Rejected => FulfillmentStatus::Rejected,
                FulfillmentReviewDecision::ClarificationRequested => FulfillmentStatus::ClarificationRequested,
            };

            if ($status === FulfillmentStatus::Accepted) {
                $accepted = $this->progress->acceptedQuantity($locked->commitment);

                abort_if(
                    QuantityAmount::compare(
                        QuantityAmount::add($accepted, $locked->quantity),
                        $locked->commitment->quantity,
                    ) > 0,
                    422,
                    'Accepting this Fulfillment would exceed the Commitment quantity.',
                );
            }

            $actor = Actor::query()->findOrFail($current->actor->id);
            $reviewedAt = now();

            $review = FulfillmentReview::query()->create([
                'fulfillment_id' => $locked->id,
                'reviewer_actor_id' => $actor->id,
                'decision' => $decision,
                'note' => $note !== '' ? $note : null,
                'reviewed_at' => $reviewedAt,
            ]);

            $locked->transition($status, $reviewedAt);

            CommitmentEvent::query()->create([
                'commitment_id' => $locked->commitment_id,
                'actor_id' => $actor->id,
                'event_type' => CommitmentEventType::FulfillmentReviewed,
                'payload' => [
                    'fulfillment_uuid' => $locked->uuid,
                    'review_uuid' => $review->uuid,
                    'decision' => $decision->value,
                    'note' => $review->note,
                ],
            ]);

            return $review->fresh([
                'fulfillment.commitment',
                'reviewer.user',
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
