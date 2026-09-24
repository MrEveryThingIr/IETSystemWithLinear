<?php

namespace App\Actions\Accounting;

use App\Models\MonetaryUnit;
use App\Support\MonetaryUnitCatalog;

class EnsureMonetaryUnit
{
    public function execute(string $code): MonetaryUnit
    {
        $code = strtoupper(trim($code));
        $meta = MonetaryUnitCatalog::get($code);

        $unit = MonetaryUnit::query()->firstOrCreate(
            ['code' => $code],
            [
                'name' => $meta['name'],
                'symbol' => $meta['symbol'],
                'exponent' => $meta['exponent'],
            ],
        );

        abort_unless((int) $unit->exponent === $meta['exponent'], 422, 'Monetary unit exponent mismatch.');

        return $unit;
    }
}
