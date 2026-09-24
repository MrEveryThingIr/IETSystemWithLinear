<?php

namespace App\Support;

final class MonetaryUnitCatalog
{
    /** @return array<string, array{name: string, exponent: int, symbol: string}> */
    public static function all(): array
    {
        return [
            'EUR' => ['name' => 'Euro', 'exponent' => 2, 'symbol' => '€'],
            'USD' => ['name' => 'US Dollar', 'exponent' => 2, 'symbol' => '$'],
            'GBP' => ['name' => 'Pound Sterling', 'exponent' => 2, 'symbol' => '£'],
            'JPY' => ['name' => 'Japanese Yen', 'exponent' => 0, 'symbol' => '¥'],
            'CNY' => ['name' => 'Chinese Yuan', 'exponent' => 2, 'symbol' => '¥'],
            'AED' => ['name' => 'UAE Dirham', 'exponent' => 2, 'symbol' => 'د.إ'],
        ];
    }

    /** @return array{name: string, exponent: int, symbol: string} */
    public static function get(string $code): array
    {
        $code = strtoupper(trim($code));
        $unit = self::all()[$code] ?? null;

        abort_unless(is_array($unit), 422, 'Unsupported monetary unit.');

        return $unit;
    }
}
