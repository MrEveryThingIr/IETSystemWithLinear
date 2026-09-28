<?php

namespace App\Support;

use App\Actions\Iet\EnsureIetEconomy;
use App\Models\IetRateVersion;

final class IetPricing
{
    public function __construct(
        private readonly EnsureIetEconomy $economy,
        private readonly IetRateMath $math,
    ) {}

    /** @return array{rate:IetRateVersion,usd_minor:int,iet_minor:int} */
    public function quoteUsdMinor(int $usdMinor): array
    {
        abort_if($usdMinor <= 0 || $usdMinor > 1_000_000_000, 422, __('iet.validation.usd_amount'));

        $rate = $this->economy->currentRate();

        return [
            'rate' => $rate,
            'usd_minor' => $usdMinor,
            'iet_minor' => $this->math->ietForUsdMinor($usdMinor, $rate),
        ];
    }

    /** @return array{rate:IetRateVersion,usd_minor:int,iet_minor:int} */
    public function quoteIetMinor(int $ietMinor): array
    {
        abort_if($ietMinor <= 0, 422, __('iet.validation.iet_amount'));

        $rate = $this->economy->currentRate();

        return [
            'rate' => $rate,
            'usd_minor' => $this->math->usdMinorForIet($ietMinor, $rate),
            'iet_minor' => $ietMinor,
        ];
    }
}
