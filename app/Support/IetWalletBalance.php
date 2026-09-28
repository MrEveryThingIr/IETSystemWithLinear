<?php

namespace App\Support;

use App\Actions\Iet\EnsureIetWallet;
use App\Models\User;

final class IetWalletBalance
{
    public function __construct(private readonly EnsureIetWallet $wallets) {}

    public function forUser(User $user): int
    {
        $wallet = $this->wallets->execute($user)['wallet'];

        return (int) $wallet->journalLines()
            ->selectRaw('COALESCE(SUM(debit_minor), 0) - COALESCE(SUM(credit_minor), 0) AS balance_minor')
            ->value('balance_minor');
    }

    public function assertAtLeast(User $user, int $requiredMinor): void
    {
        abort_if(
            $requiredMinor <= 0 || $this->forUser($user) < $requiredMinor,
            422,
            __('iet.validation.insufficient_balance'),
        );
    }
}
