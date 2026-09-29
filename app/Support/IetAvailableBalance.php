<?php

namespace App\Support;

use App\IetExchangeDirection;
use App\IetExchangeStatus;
use App\Models\Account;
use App\Models\IetExchangeRequest;
use App\Models\User;

final class IetAvailableBalance
{
    public function __construct(private readonly AccountingSummary $summary) {}

    public function forUser(User $user, Account $wallet): int
    {
        $balance = $this->summary->accountBalanceMinor($wallet);

        $reserved = (int) IetExchangeRequest::query()
            ->where('user_id', $user->id)
            ->where('direction', IetExchangeDirection::Cashout->value)
            ->where('status', IetExchangeStatus::Pending->value)
            ->sum('iet_amount');

        return max(0, $balance - $reserved);
    }
}
