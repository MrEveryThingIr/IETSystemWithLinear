<?php

namespace App\Support;

use App\FulfillmentStatus;
use App\Models\Actor;
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
     *   pending_minor: int,
     *   outstanding_minor: int,
     *   available_minor: int,
     *   disputed_minor: int,
     *   obligation_count: int,
     *   confirmed_settlement_count: int
     * }
     */
    public function forContractUnit(
        Contract $contract,
        MonetaryUnit $unit,
        ?Actor $actor = null,
    ): array {
        $scheduledCount = PlanOccurrence::query()
            ->whereHas(
                'plan.commitmentBinding.commitment',
                function ($query) use ($contract, $actor): void {
                    $query->whereHas(
                        'contractVersion',
                        fn ($query) => $query->where('contract_id', $contract->id),
                    );

                    if ($actor instanceof Actor) {
                        $query->where(function ($query) use ($actor): void {
                            $query->where('obligor_actor_id', $actor->id)
                                ->orWhere('beneficiary_actor_id', $actor->id);
                        });
                    }
                },
            )
            ->where('status', '!=', PlanOccurrenceStatus::Cancelled->value)
            ->count();

        $fulfillmentQuery = Fulfillment::query()
            ->whereHas(
                'commitment',
                function ($query) use ($contract, $actor): void {
                    $query->whereHas(
                        'contractVersion',
                        fn ($query) => $query->where('contract_id', $contract->id),
                    );

                    if ($actor instanceof Actor) {
                        $query->where(function ($query) use ($actor): void {
                            $query->where('obligor_actor_id', $actor->id)
                                ->orWhere('beneficiary_actor_id', $actor->id);
                        });
                    }
                },
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

        $obligationQuery = FinancialObligation::query()
            ->where('monetary_unit_id', $unit->id)
            ->whereHas(
                'contractVersion',
                fn ($query) => $query->where('contract_id', $contract->id),
            );

        if ($actor instanceof Actor) {
            $obligationQuery->where(function ($query) use ($actor): void {
                $query->where('debtor_actor_id', $actor->id)
                    ->orWhere('creditor_actor_id', $actor->id);
            });
        }

        $obligations = $obligationQuery
            ->with(['fulfillment', 'settlements'])
            ->get();

        $earned = 0;
        $paid = 0;
        $pending = 0;
        $outstanding = 0;
        $disputed = 0;
        $confirmedSettlementCount = 0;

        foreach ($obligations as $obligation) {
            $confirmed = (int) $obligation->settlements
                ->where('status', SettlementStatus::Confirmed)
                ->sum('amount_minor');

            $pendingForObligation = (int) $obligation->settlements
                ->where('status', SettlementStatus::PendingConfirmation)
                ->sum('amount_minor');

            $paid += $confirmed;
            $pending += $pendingForObligation;
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
            'pending_minor' => $pending,
            'outstanding_minor' => $outstanding,
            'available_minor' => max(0, $outstanding - $pending),
            'disputed_minor' => $disputed,
            'obligation_count' => $obligations->count(),
            'confirmed_settlement_count' => $confirmedSettlementCount,
        ];
    }

    /**
     * @return list<array{
     *   date: string,
     *   fulfillment_count: int,
     *   accepted_count: int,
     *   disputed_count: int,
     *   earned_minor: int,
     *   pending_minor: int,
     *   paid_minor: int,
     *   outstanding_minor: int
     * }>
     */
    public function dailyForContractUnit(
        Contract $contract,
        MonetaryUnit $unit,
        string $timezone,
        ?Actor $actor = null,
    ): array {
        $query = FinancialObligation::query()
            ->where('monetary_unit_id', $unit->id)
            ->whereHas(
                'contractVersion',
                fn ($query) => $query->where('contract_id', $contract->id),
            );

        if ($actor instanceof Actor) {
            $query->where(function ($query) use ($actor): void {
                $query->where('debtor_actor_id', $actor->id)
                    ->orWhere('creditor_actor_id', $actor->id);
            });
        }

        $rows = [];

        foreach ($query->with(['fulfillment', 'settlements'])->get() as $obligation) {
            $workAt = $obligation->fulfillment->actual_end_at
                ?? $obligation->fulfillment->actual_start_at
                ?? $obligation->fulfillment->submitted_at
                ?? $obligation->recognized_at;

            $date = $workAt->setTimezone($timezone)->format('Y-m-d');

            $rows[$date] ??= [
                'date' => $date,
                'fulfillment_count' => 0,
                'accepted_count' => 0,
                'disputed_count' => 0,
                'earned_minor' => 0,
                'pending_minor' => 0,
                'paid_minor' => 0,
                'outstanding_minor' => 0,
            ];

            $rows[$date]['fulfillment_count']++;

            $confirmed = (int) $obligation->settlements
                ->where('status', SettlementStatus::Confirmed)
                ->sum('amount_minor');
            $pending = (int) $obligation->settlements
                ->where('status', SettlementStatus::PendingConfirmation)
                ->sum('amount_minor');
            $remaining = max(0, (int) $obligation->amount_minor - $confirmed);

            $rows[$date]['pending_minor'] += $pending;
            $rows[$date]['paid_minor'] += $confirmed;

            if ($obligation->fulfillment->status === FulfillmentStatus::Accepted) {
                $rows[$date]['accepted_count']++;
                $rows[$date]['earned_minor'] += (int) $obligation->amount_minor;
                $rows[$date]['outstanding_minor'] += $remaining;
            } elseif ($obligation->fulfillment->status === FulfillmentStatus::Disputed) {
                $rows[$date]['disputed_count']++;
            }
        }

        krsort($rows);

        return array_values($rows);
    }

}
