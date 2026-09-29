<?php

namespace App\Support;

use App\ContractStatus;
use App\FulfillmentStatus;
use App\Models\Contract;
use App\Models\Proposal;
use App\Models\Relationship;
use App\ProposalStatus;
use App\RelationshipStatus;

final class DealPipeline
{
    /** @return list<string> */
    public function steps(): array
    {
        return ['connect', 'negotiate', 'agree', 'work', 'review', 'settle'];
    }

    public function stage(Relationship $relationship): string
    {
        if (in_array($relationship->status, [RelationshipStatus::Ended, RelationshipStatus::Cancelled], true)) {
            return 'closed';
        }

        if ($relationship->status === RelationshipStatus::Proposed) {
            return 'connect';
        }

        $contract = $relationship->contracts
            ->sortByDesc('id')
            ->first();

        if ($contract instanceof Contract) {
            if ($contract->status !== ContractStatus::Active) {
                return 'agree';
            }

            $fulfillments = $contract->versions
                ->flatMap(fn ($version) => $version->commitments)
                ->flatMap(fn ($commitment) => $commitment->fulfillments);

            if ($fulfillments->contains(fn ($fulfillment): bool => in_array($fulfillment->status, [
                FulfillmentStatus::Submitted,
                FulfillmentStatus::ClarificationRequested,
                FulfillmentStatus::Rejected,
                FulfillmentStatus::Corrected,
                FulfillmentStatus::Disputed,
            ], true))) {
                return 'review';
            }

            if ($fulfillments->contains(fn ($fulfillment): bool => $fulfillment->status === FulfillmentStatus::Accepted)) {
                return 'settle';
            }

            return 'work';
        }

        $proposal = $relationship->proposals
            ->sortByDesc('id')
            ->first();

        if (! $proposal instanceof Proposal) {
            return 'negotiate';
        }

        return $proposal->status === ProposalStatus::Accepted
            ? 'agree'
            : 'negotiate';
    }

    public function nextAction(Relationship $relationship): string
    {
        return match ($this->stage($relationship)) {
            'connect' => 'respond',
            'negotiate' => 'proposal',
            'agree' => 'contract',
            'work' => 'work',
            'review' => 'review',
            'settle' => 'settle',
            default => 'history',
        };
    }
}
