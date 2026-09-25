<?php

namespace App\Support;

use App\FulfillmentStatus;
use App\Models\Contract;
use App\Models\FinancialObligation;
use App\Models\Fulfillment;
use App\Models\MonetaryUnit;
use App\Models\PlanOccurrence;
use App\PlanOccurrenceStatus;
use App\SettlementStatus;

final class ContractFinancialSummary
{
    /**
     * @return array{
     *   scheduled_count: int,
     *   worked_count: int,
     *   accepted_count: int,
     *   disputed_count: int,
     *   earned_minor: int,
     *   paid_minor: int,
     *   outstanding_minor: int,
     *   disputed_minor: int,
     *   obligation_count: int,
     *   confirmed_settlement_count: int
     * }
     */
    public function forContractUnit(Contract $contract, MonetaryUnit $unit): array
    {
        $scheduledCount = PlanOccurrence::query()
            ->whereHas(
                'plan.commitmentBinding.commitment.contractVersion',
                fn ($query) => $query->where('contract_id', $contract->id),
            )
            ->where('status', '!=', PlanOccurrenceStatus::Cancelled->value)
            ->count();

        $fulfillmentQuery = Fulfillment::query()
            ->whereHas(
                'commitment.contractVersion',
                fn ($query) => $query->where('contract_id', $contract->id),
            );

        $workedCount = (clone $fulfillmentQuery)
            ->where('status', '!=', FulfillmentStatus::Corrected->value)
            ->count();

        $acceptedCount = (clone $fulfillmentQuery)
            ->where('status', FulfillmentStatus::Accepted->value)
            ->count();

        $disputedCount = (clone $fulfillmentQuery)
            ->where('status', FulfillmentStatus::Disputed->value)
            ->count();

        $obligations = FinancialObligation::query()
            ->where('monetary_unit_id', $unit->id)
            ->whereHas(
                'contractVersion',
                fn ($query) => $query->where('contract_id', $contract->id),
            )
            ->with(['fulfillment', 'settlements'])
            ->get();

        $earned = 0;
        $paid = 0;
        $outstanding = 0;
        $disputed = 0;
        $confirmedSettlementCount = 0;

        foreach ($obligations as $obligation) {
            $confirmed = $obligation->settlements
                ->where('status', SettlementStatus::Confirmed)
                ->sum('amount_minor');

            $confirmed = (int) $confirmed;
            $paid += $confirmed;
            $confirmedSettlementCount += $obligation->settlements
                ->where('status', SettlementStatus::Confirmed)
                ->count();

            $remaining = max(0, (int) $obligation->amount_minor - $confirmed);

            if ($obligation->fulfillment->status === FulfillmentStatus::Accepted) {
                $earned += (int) $obligation->amount_minor;
                $outstanding += $remaining;
            } elseif ($obligation->fulfillment->status === FulfillmentStatus::Disputed) {
                $disputed += $remaining;
            }
        }

        return [
            'scheduled_count' => $scheduledCount,
            'worked_count' => $workedCount,
            'accepted_count' => $acceptedCount,
            'disputed_count' => $disputedCount,
            'earned_minor' => $earned,
            'paid_minor' => $paid,
            'outstanding_minor' => $outstanding,
            'disputed_minor' => $disputed,
            'obligation_count' => $obligations->count(),
            'confirmed_settlement_count' => $confirmedSettlementCount,
        ];
    }
}
