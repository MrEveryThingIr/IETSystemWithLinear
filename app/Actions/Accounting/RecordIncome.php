<?php

namespace App\Actions\Accounting;

use App\AccountType;
use App\JournalEntryKind;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\Ledger;
use App\Models\User;

class RecordIncome
{
    public function __construct(
        private readonly CreateLedgerAccount $accounts,
        private readonly PostJournalEntry $entries,
    ) {}

    public function execute(
        Ledger $ledger,
        User $user,
        Account $receivingAccount,
        int $amountMinor,
        string $occurredOn,
        string $category = 'General income',
        ?string $description = null,
    ): JournalEntry {
        abort_unless($receivingAccount->type === AccountType::Asset, 422, 'Income receipt requires an Asset Account.');
        abort_if($amountMinor <= 0, 422, 'Income amount must be positive.');

        $category = trim($category);
        $income = $category === '' || $category === 'General income'
            ? $this->accounts->execute($ledger, $user, 'General income', AccountType::Income, 'general_income')
            : $this->accounts->execute($ledger, $user, $category, AccountType::Income);

        return $this->entries->execute(
            $ledger,
            $user,
            JournalEntryKind::Income,
            $occurredOn,
            $description ?? $category,
            [
                ['account' => $receivingAccount, 'debit_minor' => $amountMinor],
                ['account' => $income, 'credit_minor' => $amountMinor],
            ],
        );
    }
}
