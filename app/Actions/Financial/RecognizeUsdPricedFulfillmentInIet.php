<?php

namespace App\Actions\Financial;

use App\Models\Fulfillment;
use App\Models\IetPricedFinancialObligation;
use App\Models\User;
use App\Support\IetPricing;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class RecognizeUsdPricedFulfillmentInIet
{
    public function __construct(
        private readonly RecognizeFulfillmentFinancialObligation $recognize,
        private readonly IetPricing $pricing,
    ) {}

    public function execute(
        Fulfillment $fulfillment,
        User $user,
        int $usdAmountMinor,
        ?CarbonInterface $dueAt = null,
        ?string $description = null,
    ): IetPricedFinancialObligation {
        abort_if($usdAmountMinor <= 0, 422, 'USD-priced obligation amount must be positive.');

        return DB::transaction(function () use ($fulfillment, $user, $usdAmountMinor, $dueAt, $description): IetPricedFinancialObligation {
            $quote = $this->pricing->currentQuote();
            $ietAmount = $this->pricing->ietForUsdMinor($usdAmountMinor, $quote);

            $obligation = $this->recognize->execute(
                $fulfillment,
                $user,
                'IET',
                $ietAmount,
                $dueAt,
                $description,
            );

            return IetPricedFinancialObligation::query()->create([
                'financial_obligation_id' => $obligation->id,
                'reference_usd_amount_minor' => $usdAmountMinor,
                'valuation_quote_id' => $quote->id,
                'iet_amount' => $ietAmount,
            ])->fresh(['obligation.monetaryUnit', 'valuationQuote']);
        });
    }
}
