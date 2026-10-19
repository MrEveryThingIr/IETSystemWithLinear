<?php

namespace App\Support;

use App\Models\ActorProfileIntent;
use App\Models\Business;
use App\Models\Contract;
use App\Models\Relationship;

/** Market activity of a Business: its needs/offers, the deals they led to, and contracts. */
final class BusinessEconomyProjection
{
    /**
     * @return array{
     *   market_intent_count:int,
     *   deal_count:int,
     *   contract_count:int
     * }
     */
    public function forBusiness(Business $business): array
    {
        $intentIds = ActorProfileIntent::query()
            ->where('metadata->business_uuid', $business->uuid)
            ->pluck('id');

        $relationshipIds = $intentIds->isEmpty()
            ? collect()
            : Relationship::query()
                ->where(function ($query) use ($intentIds): void {
                    $query->whereIn('originating_intent_id', $intentIds)
                        ->orWhereIn('matched_intent_id', $intentIds);
                })
                ->pluck('id');

        $contractIds = $relationshipIds->isEmpty()
            ? collect()
            : Contract::query()->whereIn('relationship_id', $relationshipIds)->pluck('id');

        return [
            'market_intent_count' => $intentIds->count(),
            'deal_count' => $relationshipIds->count(),
            'contract_count' => $contractIds->count(),
        ];
    }
}
