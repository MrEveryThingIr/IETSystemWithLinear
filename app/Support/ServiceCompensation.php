<?php

namespace App\Support;

use App\Models\ContractServiceTerm;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;
use OverflowException;

final class ServiceCompensation
{
    public function amountMinor(ContractServiceTerm $terms, string|int $quantity): int
    {
        $scaledQuantity = QuantityAmount::toScaledInt($quantity);
        $unitRate = (int) $terms->unit_rate_minor;

        if ($scaledQuantity <= 0 || $unitRate <= 0) {
            throw new InvalidArgumentException('Service quantity and unit rate must be positive.');
        }

        if ($scaledQuantity > intdiv(PHP_INT_MAX, $unitRate)) {
            throw new OverflowException('Service compensation exceeds the supported integer range.');
        }

        $product = $scaledQuantity * $unitRate;
        $scale = 10 ** QuantityAmount::SCALE;

        // Positive amounts use deterministic half-up rounding to the MonetaryUnit minor unit.
        return intdiv($product + intdiv($scale, 2), $scale);
    }

    public function dueAt(
        ContractServiceTerm $terms,
        ?CarbonInterface $workAt = null,
    ): CarbonImmutable {
        $timezone = $terms->timezone;
        $work = $workAt === null
            ? CarbonImmutable::now($timezone)
            : CarbonImmutable::parse($workAt->toIso8601String())->setTimezone($timezone);

        $base = match ($terms->settlement_cycle) {
            ContractServiceTerm::SETTLEMENT_WEEKLY => $work->endOfWeek(),
            ContractServiceTerm::SETTLEMENT_MONTHLY => $work->endOfMonth(),
            ContractServiceTerm::SETTLEMENT_CONTRACT_END => $terms->plan_ends_on !== null
                ? CarbonImmutable::parse($terms->plan_ends_on->format('Y-m-d'), $timezone)->endOfDay()
                : $work,
            default => $work,
        };

        return $base->addDays((int) $terms->payment_due_days)->utc();
    }
}
