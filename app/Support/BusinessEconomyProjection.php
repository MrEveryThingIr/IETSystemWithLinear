<?php

namespace App\Support;

use App\Models\ActorProfileIntent;
use App\Models\Business;
use App\Models\Contract;
use App\Models\ContractVersion;
use App\Models\FinancialObligation;
use App\Models\MonetaryUnit;
use App\Models\Relationship;
use App\Models\Settlement;
use App\SettlementStatus;

final class BusinessEconomyProjection
{
    /**
     * @return array{
     *   market_intent_count:int,
     *   deal_count:int,
     *   contract_count:int,
     *   iet_receivable_minor:int,
     *   iet_payable_minor:int,
     *   iet_net_minor:int
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

        $versionIds = $contractIds->isEmpty()
            ? collect()
            : ContractVersion::query()->whereIn('contract_id', $contractIds)->pluck('id');

        $unitId = MonetaryUnit::query()->where('code', 'IET')->value('id');

        $memberActorIds = $business->memberships()
            ->where('status', 'active')
            ->pluck('actor_id')
            ->push($business->owner_actor_id)
            ->unique()
            ->values();

        $receivable = 0;
        $payable = 0;

        if ($unitId !== null && $versionIds->isNotEmpty() && $memberActorIds->isNotEmpty()) {
            $obligations = FinancialObligation::query()
                ->where('monetary_unit_id', $unitId)
                ->whereIn('contract_version_id', $versionIds)
                ->where(function ($query) use ($memberActorIds): void {
                    $query->whereIn('debtor_actor_id', $memberActorIds)
                        ->orWhereIn('creditor_actor_id', $memberActorIds);
                })
                ->with(['settlements' => fn ($query) => $query
                    ->where('status', SettlementStatus::Confirmed->value)])
                ->get();

            foreach ($obligations as $obligation) {
                $confirmed = $obligation->settlements->sum(
                    fn (Settlement $settlement): int => (int) $settlement->amount_minor,
                );
                $outstanding = max(0, (int) $obligation->amount_minor - $confirmed);

                $debtorIsBusiness = $memberActorIds->contains((int) $obligation->debtor_actor_id);
                $creditorIsBusiness = $memberActorIds->contains((int) $obligation->creditor_actor_id);

                if ($creditorIsBusiness && ! $debtorIsBusiness) {
                    $receivable += $outstanding;
                }

                if ($debtorIsBusiness && ! $creditorIsBusiness) {
                    $payable += $outstanding;
                }
            }
        }

        return [
            'market_intent_count' => $intentIds->count(),
            'deal_count' => $relationshipIds->count(),
            'contract_count' => $contractIds->count(),
            'iet_receivable_minor' => $receivable,
            'iet_payable_minor' => $payable,
            'iet_net_minor' => $receivable - $payable,
        ];
    }
}
