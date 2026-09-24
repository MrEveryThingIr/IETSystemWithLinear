<?php

namespace App\Actions\Accounting;

use App\AccountType;
use App\JournalEntryKind;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\Ledger;
use App\Models\User;

class RecordExpense
{
    public function __construct(
        private readonly CreateLedgerAccount $accounts,
        private readonly PostJournalEntry $entries,
    ) {}

    public function execute(
        Ledger $ledger,
        User $user,
        Account $paymentAccount,
        int $amountMinor,
        string $occurredOn,
        string $category = 'General expense',
        ?string $description = null,
    ): JournalEntry {
        abort_unless($paymentAccount->type === AccountType::Asset, 422, 'Expense payment requires an Asset Account.');
        abort_if($amountMinor <= 0, 422, 'Expense amount must be positive.');

        $category = trim($category);
        $expense = $category === '' || $category === 'General expense'
            ? $this->accounts->execute($ledger, $user, 'General expense', AccountType::Expense, 'general_expense')
            : $this->accounts->execute($ledger, $user, $category, AccountType::Expense);

        return $this->entries->execute(
            $ledger,
            $user,
            JournalEntryKind::Expense,
            $occurredOn,
            $description ?? $category,
            [
                ['account' => $expense, 'debit_minor' => $amountMinor],
                ['account' => $paymentAccount, 'credit_minor' => $amountMinor],
            ],
        );
    }
}
