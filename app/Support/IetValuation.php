<?php

namespace App\Support;

use App\Models\IetValuationSnapshot;
final class IetValuation
{
    public function current(): IetValuationSnapshot
    {
        $current = IetValuationSnapshot::query()
            ->where('effective_at', '<=', now())
            ->latest('effective_at')
            ->latest('id')
            ->first();

        abort_unless($current instanceof IetValuationSnapshot, 500, 'IET valuation bootstrap is missing.');

        return $current;
    }

    /** @return array{snapshot:IetValuationSnapshot, iet_minor:int} */
    public function quoteUsdMinor(int $usdMinor): array
    {
        $snapshot = $this->current();

        return [
            'snapshot' => $snapshot,
            'iet_minor' => IetValueMath::ietMinorForUsdMinor(
                $usdMinor,
                $snapshot->usd_pico_per_iet,
            ),
        ];
    }
}
