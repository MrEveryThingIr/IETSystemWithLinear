<?php

namespace App\Actions\Iet;

use App\Actions\Accounting\EnsureMonetaryUnit;
use App\IetExchangeDirection;
use App\Models\IetExchangeRequest;
use App\Models\User;
use App\Support\IetPricing;
use App\Support\IetWalletBalance;

final class CreateIetExchangeRequest
{
    public function __construct(
        private readonly EnsureMonetaryUnit $units,
        private readonly IetPricing $pricing,
        private readonly IetWalletBalance $balances,
    ) {}

    public function deposit(
        User $user,
        int $usdMinor,
        ?string $note = null,
    ): IetExchangeRequest {
        $quote = $this->pricing->quoteUsdMinor($usdMinor);
        $usd = $this->units->execute('USD');

        return IetExchangeRequest::query()->create([
            'user_id' => $user->id,
            'direction' => IetExchangeDirection::Deposit,
            'rate_version_id' => $quote['rate']->id,
            'fiat_unit_id' => $usd->id,
            'fiat_amount_minor' => $quote['usd_minor'],
            'iet_amount_minor' => $quote['iet_minor'],
            'note' => $this->note($note),
        ]);
    }

    public function cashout(
        User $user,
        int $ietMinor,
        ?string $note = null,
    ): IetExchangeRequest {
        $quote = $this->pricing->quoteIetMinor($ietMinor);
        abort_if($quote['usd_minor'] <= 0, 422, __('iet.validation.cashout_too_small'));
        $this->balances->assertAtLeast($user, $ietMinor);

        $usd = $this->units->execute('USD');

        return IetExchangeRequest::query()->create([
            'user_id' => $user->id,
            'direction' => IetExchangeDirection::Cashout,
            'rate_version_id' => $quote['rate']->id,
            'fiat_unit_id' => $usd->id,
            'fiat_amount_minor' => $quote['usd_minor'],
            'iet_amount_minor' => $quote['iet_minor'],
            'note' => $this->note($note),
        ]);
    }

    private function note(?string $note): ?string
    {
        $note = trim((string) $note);
        abort_if(mb_strlen($note) > 2000, 422, 'Exchange note is too long.');

        return $note !== '' ? $note : null;
    }
}
