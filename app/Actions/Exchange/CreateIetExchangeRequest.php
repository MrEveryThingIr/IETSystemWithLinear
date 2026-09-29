<?php

namespace App\Actions\Exchange;

use App\IetExchangeDirection;
use App\Models\IetExchangeRequest;
use App\Models\Ledger;
use App\Models\User;
use App\Support\IetAvailableBalance;
use App\Support\IetPricing;
use Illuminate\Support\Facades\DB;

class CreateIetExchangeRequest
{
    public function __construct(
        private readonly IetPricing $pricing,
        private readonly EnsureIetWallet $wallets,
        private readonly IetAvailableBalance $available,
    ) {}

    public function execute(
        User $user,
        IetExchangeDirection $direction,
        int $usdAmountMinor,
        ?string $externalReference = null,
        ?string $note = null,
    ): IetExchangeRequest {
        abort_if($usdAmountMinor <= 0, 422, 'Exchange amount must be positive.');

        $quote = $this->pricing->currentQuote();
        $ietAmount = $this->pricing->ietForUsdMinor($usdAmountMinor, $quote);

        return DB::transaction(function () use (
            $user,
            $direction,
            $usdAmountMinor,
            $quote,
            $ietAmount,
            $externalReference,
            $note,
        ): IetExchangeRequest {
            if ($direction === IetExchangeDirection::Cashout) {
                $wallet = $this->wallets->execute($user);

                Ledger::query()
                    ->whereKey($wallet['ledger']->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                abort_if(
                    $this->available->forUser($user, $wallet['wallet']) < $ietAmount,
                    422,
                    'Insufficient available IET balance for this cash-out request.',
                );
            }

            return IetExchangeRequest::query()->create([
                'user_id' => $user->id,
                'direction' => $direction,
                'external_unit_code' => 'USD',
                'external_amount_minor' => $usdAmountMinor,
                'valuation_quote_id' => $quote->id,
                'iet_amount' => $ietAmount,
                'external_reference' => filled($externalReference) ? trim((string) $externalReference) : null,
                'note' => filled($note) ? trim((string) $note) : null,
            ])->fresh(['valuationQuote', 'user']);
        }, attempts: 3);
    }
}
