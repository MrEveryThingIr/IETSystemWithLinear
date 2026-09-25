<?php

namespace App\Support;

use App\FulfillmentStatus;
use App\Models\Commitment;
use App\Models\Fulfillment;

final class CommitmentProgress
{
    public function acceptedQuantity(Commitment $commitment): string
    {
        return $commitment->fulfillments()
            ->where('status', FulfillmentStatus::Accepted->value)
            ->get(['quantity'])
            ->reduce(
                fn (string $sum, Fulfillment $fulfillment): string => QuantityAmount::add($sum, $fulfillment->quantity),
                QuantityAmount::normalize('0'),
            );
    }

    public function remainingQuantity(Commitment $commitment): string
    {
        return QuantityAmount::subtractFloorZero(
            $commitment->quantity,
            $this->acceptedQuantity($commitment),
        );
    }

    public function isSatisfied(Commitment $commitment): bool
    {
        return QuantityAmount::compare(
            $this->acceptedQuantity($commitment),
            $commitment->quantity,
        ) >= 0;
    }
}
