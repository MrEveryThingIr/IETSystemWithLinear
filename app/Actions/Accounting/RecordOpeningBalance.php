<?php

namespace App\Actions\Accounting;

use App\AccountType;
use App\JournalEntryKind;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\Ledger;
use App\Models\User;

class RecordOpeningBalance
{
    public function __construct(
        private readonly CreateLedgerAccount $accounts,
        private readonly PostJournalEntry $entries,
    ) {}

    public function execute(
        Ledger $ledger,
        User $user,
        Account $assetAccount,
        int $amountMinor,
        string $occurredOn,
        ?string $description = null,
    ): JournalEntry {
        abort_unless($assetAccount->type === AccountType::Asset, 422, 'Opening balance requires an Asset Account.');
        abort_if($amountMinor <= 0, 422, 'Opening balance must be positive.');

        $equity = $this->accounts->execute($ledger, $user, 'Opening balance', AccountType::Equity, 'opening_equity');

        return $this->entries->execute(
            $ledger,
            $user,
            JournalEntryKind::OpeningBalance,
            $occurredOn,
            $description ?? 'Opening balance',
            [
                ['account' => $assetAccount, 'debit_minor' => $amountMinor],
                ['account' => $equity, 'credit_minor' => $amountMinor],
            ],
        );
    }
}
