<?php

namespace App\Actions\Iet;

use App\Actions\Accounting\EnsureMonetaryUnit;
use App\Models\IetRateVersion;
use App\Models\MonetaryUnit;
use Illuminate\Support\Facades\DB;

final class EnsureIetEconomy
{
    public function __construct(private readonly EnsureMonetaryUnit $units) {}

    /** @return array{unit:MonetaryUnit,rate:IetRateVersion} */
    public function execute(): array
    {
        return DB::transaction(function (): array {
            $unit = $this->units->execute('IET');

            $rate = IetRateVersion::query()
                ->orderByDesc('effective_at')
                ->orderByDesc('sequence')
                ->lockForUpdate()
                ->first();

            if (! $rate instanceof IetRateVersion) {
                $rate = IetRateVersion::query()->create([
                    'sequence' => 1,
                    'usd_numerator' => 1,
                    'usd_denominator' => 100_000_000,
                    'adjustment_ppm' => null,
                    'reason' => 'Initial IET reference rate: 1 IET = 0.000001% of 1 USD.',
                    'criteria' => ['bootstrap' => true],
                    'effective_at' => now(),
                    'created_by_user_id' => null,
                ]);
            }

            return compact('unit', 'rate');
        }, attempts: 3);
    }

    public function currentRate(): IetRateVersion
    {
        $this->execute();

        return IetRateVersion::query()
            ->where('effective_at', '<=', now())
            ->orderByDesc('effective_at')
            ->orderByDesc('sequence')
            ->firstOrFail();
    }
}
