<?php

namespace App\Support;

use App\Models\IetValuationSnapshot;
use Illuminate\Support\Facades\DB;

final class IetValuation
{
    public function current(): IetValuationSnapshot
    {
        return DB::transaction(function (): IetValuationSnapshot {
            $current = IetValuationSnapshot::query()
                ->where('effective_at', '<=', now())
                ->latest('effective_at')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($current instanceof IetValuationSnapshot) {
                return $current;
            }

            return IetValuationSnapshot::query()->create([
                'usd_pico_per_iet' => IetValueMath::INITIAL_USD_PICO_PER_IET,
                'source' => 'initial',
                'factors' => [
                    'x_percent' => IetValueMath::INITIAL_X_PERCENT,
                    'policy' => 'bootstrap',
                ],
                'note' => 'Initial IET internal settlement valuation.',
                'effective_at' => now(),
                'created_by_actor_id' => null,
            ]);
        }, attempts: 3);
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
