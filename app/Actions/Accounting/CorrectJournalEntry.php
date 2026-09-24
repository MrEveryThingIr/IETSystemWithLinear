<?php

namespace App\Actions\Accounting;

use App\JournalEntryKind;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CorrectJournalEntry
{
    public function __construct(
        private readonly ReverseJournalEntry $reverse,
        private readonly PostJournalEntry $entries,
    ) {}

    /**
     * @param  list<array{account: Account, debit_minor?: int, credit_minor?: int, memo?: ?string}>  $replacementLines
     * @return array{reversal: JournalEntry, correction: JournalEntry}
     */
    public function execute(
        JournalEntry $entry,
        User $user,
        string $occurredOn,
        JournalEntryKind $replacementKind,
        ?string $replacementDescription,
        array $replacementLines,
    ): array {
        abort_unless($replacementKind !== JournalEntryKind::Reversal, 422, 'Correction replacement cannot itself be a reversal.');

        return DB::transaction(function () use (
            $entry,
            $user,
            $occurredOn,
            $replacementKind,
            $replacementDescription,
            $replacementLines,
        ): array {
            $reversal = $this->reverse->execute($entry, $user, $occurredOn, 'Correction reversal');

            $correction = $this->entries->execute(
                $entry->ledger()->firstOrFail(),
                $user,
                $replacementKind === JournalEntryKind::Correction ? JournalEntryKind::Correction : $replacementKind,
                $occurredOn,
                $replacementDescription ?? 'Correction',
                $replacementLines,
                correctionOf: $entry,
            );

            return ['reversal' => $reversal, 'correction' => $correction];
        }, attempts: 3);
    }
}
