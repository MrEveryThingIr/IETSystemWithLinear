<?php

namespace App\Support;

final class SettlementMethod
{
    public static function usesIetWallet(?string $method): bool
    {
        if ($method === null || trim($method) === '') {
            return true;
        }

        return strtolower(trim($method)) !== 'external_cash';
    }
}
