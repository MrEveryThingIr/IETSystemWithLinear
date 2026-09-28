<?php

namespace App\Support;

use App\Models\IetValuationSnapshot;
use App\Models\User;

final class IetRequirement
{
    public function __construct(
        private readonly IetValuation $valuation,
        private readonly IetWallet $wallet,
    ) {}

    /** @return array{snapshot:IetValuationSnapshot, iet_minor:int, available_minor:int} */
    public function quoteForUsdMinor(User $user, int $usdMinor): array
    {
        $quote = $this->valuation->quoteUsdMinor($usdMinor);

        return [
            'snapshot' => $quote['snapshot'],
            'iet_minor' => $quote['iet_minor'],
            'available_minor' => $this->wallet->availableMinor($user),
        ];
    }

    /** @return array{snapshot:IetValuationSnapshot, iet_minor:int, available_minor:int} */
    public function requireForUsdMinor(User $user, int $usdMinor): array
    {
        $quote = $this->quoteForUsdMinor($user, $usdMinor);

        abort_if(
            $quote['available_minor'] < $quote['iet_minor'],
            422,
            'Insufficient IET for this system flow.',
        );

        return $quote;
    }
}
