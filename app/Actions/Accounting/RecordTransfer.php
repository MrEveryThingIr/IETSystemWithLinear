<?php

namespace App\Actions\Accounting;

use App\AccountType;
use App\JournalEntryKind;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\Ledger;
use App\Models\User;

class RecordTransfer
{
    public function __construct(private readonly PostJournalEntry $entries) {}

    public function execute(
        Ledger $ledger,
        User $user,
        Account $from,
        Account $to,
        int $amountMinor,
        string $occurredOn,
        ?string $description = null,
    ): JournalEntry {
        abort_unless($from->type === AccountType::Asset && $to->type === AccountType::Asset, 422, 'Transfers require Asset Accounts.');
        abort_if((int) $from->id === (int) $to->id, 422, 'Transfer Accounts must be different.');
        abort_if($amountMinor <= 0, 422, 'Transfer amount must be positive.');

        return $this->entries->execute(
            $ledger,
            $user,
            JournalEntryKind::Transfer,
            $occurredOn,
            $description ?? "Transfer {$from->name} → {$to->name}",
            [
                ['account' => $to, 'debit_minor' => $amountMinor],
                ['account' => $from, 'credit_minor' => $amountMinor],
            ],
        );
    }
}
