<?php

namespace App\Support;

final class SettlementMethod
{
    public static function usesIetWallet(?string $method): bool
    {
        if ($method === null || trim($method) === '') {
            return true;
        }

        return in_array(
            strtolower(trim($method)),
            ['iet', 'iet_wallet', 'internal_iet'],
            true,
        );
    }
}
