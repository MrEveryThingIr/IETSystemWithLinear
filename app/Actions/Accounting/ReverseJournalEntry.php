<?php

namespace App\Actions\Accounting;

use App\JournalEntryKind;
use App\Models\JournalEntry;
use App\Models\User;

class ReverseJournalEntry
{
    public function __construct(private readonly PostJournalEntry $entries) {}

    public function execute(
        JournalEntry $entry,
        User $user,
        string $occurredOn,
        ?string $description = null,
    ): JournalEntry {
        $entry->loadMissing(['ledger', 'lines.account']);
        abort_unless($entry->kind !== JournalEntryKind::Reversal, 422, 'A reversal cannot be reversed directly.');

        $lines = $entry->lines->map(fn ($line): array => [
            'account' => $line->account,
            'debit_minor' => $line->credit_minor,
            'credit_minor' => $line->debit_minor,
            'memo' => $line->memo,
        ])->all();

        return $this->entries->execute(
            $entry->ledger,
            $user,
            JournalEntryKind::Reversal,
            $occurredOn,
            $description ?? 'Reversal: '.($entry->description ?? $entry->uuid),
            $lines,
            reverses: $entry,
        );
    }
}
