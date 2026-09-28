<?php

namespace App\Support;

use App\IetExchangeStatus;
use App\Models\IetExchangeRequest;
use App\Models\IetInternalCharge;
use App\Models\Settlement;
use App\SettlementStatus;

final class IetValuationMetrics
{
    /** @return array<string, int> */
    public function snapshot(): array
    {
        $internalCharges = IetInternalCharge::query()->count();

        $confirmedSettlements = Settlement::query()
            ->where('status', SettlementStatus::Confirmed->value)
            ->whereHas(
                'obligation.monetaryUnit',
                fn ($query) => $query->where('code', 'IET'),
            )
            ->count();

        $confirmedDeposits = IetExchangeRequest::query()
            ->where('status', IetExchangeStatus::Confirmed->value)
            ->where('direction', 'deposit')
            ->count();

        return [
            'successful_internal_charges' => $internalCharges,
            'confirmed_iet_settlements' => $confirmedSettlements,
            'confirmed_exchange_deposits' => $confirmedDeposits,
            'successful_flow_count' => $internalCharges + $confirmedSettlements,
        ];
    }
}
