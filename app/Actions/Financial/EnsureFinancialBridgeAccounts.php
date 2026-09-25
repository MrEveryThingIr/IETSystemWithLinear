<?php

namespace App\Actions\Financial;

use App\AccountType;
use App\Actions\Accounting\CreateLedgerAccount;
use App\Actions\Accounting\CreatePersonalLedger;
use App\Models\Account;
use App\Models\Actor;
use App\Models\FinancialObligation;
use App\Models\Ledger;
use App\Models\User;

class EnsureFinancialBridgeAccounts
{
    public function __construct(
        private readonly CreatePersonalLedger $ledgers,
        private readonly CreateLedgerAccount $accounts,
    ) {}

    /**
     * @return array{
     *   ledger: Ledger,
     *   actor: Actor,
     *   recognition_debit: Account,
     *   recognition_credit: Account,
     *   settlement_debit: Account,
     *   settlement_credit: Account
     * }
     */
    public function execute(FinancialObligation $obligation, User $user): array
    {
        $current = User::query()->with('actor')->find($user->id);
        abort_unless(
            $current instanceof User
            && $current->status === 'active'
            && $current->email_verified_at !== null
            && $current->actor instanceof Actor
            && $current->actor->status === 'active',
            403,
        );

        $obligation->loadMissing(['debtor.user', 'creditor.user', 'monetaryUnit']);

        $actor = $current->actor;
        $isDebtor = (int) $actor->id === (int) $obligation->debtor_actor_id;
        $isCreditor = (int) $actor->id === (int) $obligation->creditor_actor_id;

        abort_unless($isDebtor || $isCreditor, 403);

        $ledger = $this->ledgers->execute($current, $obligation->monetaryUnit->code);
        $cash = $ledger->accounts()->where('system_key', 'cash')->firstOrFail();

        if ($isDebtor) {
            $counterparty = $obligation->creditor;
            $label = $counterparty->user?->username ?? $counterparty->uuid;

            $expense = $this->accounts->execute(
                $ledger,
                $current,
                'Contract expense',
                AccountType::Expense,
                'contract_expense',
            );
            $payable = $this->accounts->execute(
                $ledger,
                $current,
                "Payable to {$label}",
                AccountType::Liability,
                'payable_actor_'.$counterparty->id,
            );

            return [
                'ledger' => $ledger,
                'actor' => $actor,
                'recognition_debit' => $expense,
                'recognition_credit' => $payable,
                'settlement_debit' => $payable,
                'settlement_credit' => $cash,
            ];
        }

        $counterparty = $obligation->debtor;
        $label = $counterparty->user?->username ?? $counterparty->uuid;

        $receivable = $this->accounts->execute(
            $ledger,
            $current,
            "Receivable from {$label}",
            AccountType::Asset,
            'receivable_actor_'.$counterparty->id,
        );
        $income = $this->accounts->execute(
            $ledger,
            $current,
            'Contract income',
            AccountType::Income,
            'contract_income',
        );

        return [
            'ledger' => $ledger,
            'actor' => $actor,
            'recognition_debit' => $receivable,
            'recognition_credit' => $income,
            'settlement_debit' => $cash,
            'settlement_credit' => $receivable,
        ];
    }
}
