<?php

namespace App\Support;

use App\AccountType;
use App\Actions\Accounting\CreateLedgerAccount;
use App\Actions\Accounting\CreatePersonalLedger;
use App\Models\Account;
use App\Models\JournalLine;
use App\Models\Ledger;
use App\Models\User;

final class IetWallet
{
    public function __construct(
        private readonly CreatePersonalLedger $ledgers,
        private readonly CreateLedgerAccount $accounts,
    ) {}

    /**
     * @return array{
     *   ledger:Ledger,
     *   cash:Account,
     *   exchange_source:Account,
     *   exchange_sink:Account,
     *   settlement_reserve:Account,
     *   transfer_in:Account,
     *   transfer_out:Account
     * }
     */
    public function ensure(User $user): array
    {
        $ledger = $this->ledgers->execute($user, 'IET', 'IET wallet');

        $cash = $ledger->accounts()->where('system_key', 'cash')->firstOrFail();

        return [
            'ledger' => $ledger,
            'cash' => $cash,
            'exchange_source' => $this->accounts->execute(
                $ledger,
                $user,
                'IET exchange source',
                AccountType::Equity,
                'iet_exchange_source',
            ),
            'exchange_sink' => $this->accounts->execute(
                $ledger,
                $user,
                'IET exchange sink',
                AccountType::Equity,
                'iet_exchange_sink',
            ),
            'settlement_reserve' => $this->accounts->execute(
                $ledger,
                $user,
                'IET reserved for settlements',
                AccountType::Asset,
                'iet_settlement_reserve',
            ),
            'transfer_in' => $this->accounts->execute(
                $ledger,
                $user,
                'IET received internally',
                AccountType::Equity,
                'iet_transfer_in',
            ),
            'transfer_out' => $this->accounts->execute(
                $ledger,
                $user,
                'IET sent internally',
                AccountType::Equity,
                'iet_transfer_out',
            ),
        ];
    }

    public function availableMinor(User $user): int
    {
        $wallet = $this->ensure($user);

        return $this->accountBalanceMinor($wallet['cash']);
    }

    public function reservedMinor(User $user): int
    {
        $wallet = $this->ensure($user);

        return $this->accountBalanceMinor($wallet['settlement_reserve']);
    }

    public function accountBalanceMinor(Account $account): int
    {
        $row = JournalLine::query()
            ->where('account_id', $account->id)
            ->selectRaw('COALESCE(SUM(debit_minor), 0) AS debits, COALESCE(SUM(credit_minor), 0) AS credits')
            ->first();

        return (int) ($row?->debits ?? 0) - (int) ($row?->credits ?? 0);
    }
}
