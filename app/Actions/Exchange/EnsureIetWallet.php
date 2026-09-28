<?php

namespace App\Actions\Exchange;

use App\AccountType;
use App\Actions\Accounting\CreateLedgerAccount;
use App\Actions\Accounting\CreatePersonalLedger;
use App\Models\Account;
use App\Models\Ledger;
use App\Models\User;

class EnsureIetWallet
{
    public function __construct(
        private readonly CreatePersonalLedger $ledgers,
        private readonly CreateLedgerAccount $accounts,
    ) {}

    /**
     * @return array{ledger:Ledger,wallet:Account,funding:Account,internal_expense:Account}
     */
    public function execute(User $user): array
    {
        $ledger = $this->ledgers->execute($user, 'IET', 'IET Wallet');
        $wallet = $ledger->accounts()->where('system_key', 'cash')->firstOrFail();

        if ($wallet->name === 'Cash') {
            $wallet->forceFill(['name' => 'IET Wallet'])->save();
        }

        return [
            'ledger' => $ledger,
            'wallet' => $wallet->fresh(),
            'funding' => $this->accounts->execute(
                $ledger,
                $user,
                'IET Exchange funding',
                AccountType::Equity,
                'iet_exchange_funding',
            ),
            'internal_expense' => $this->accounts->execute(
                $ledger,
                $user,
                'IET internal services',
                AccountType::Expense,
                'iet_internal_services',
            ),
        ];
    }
}
