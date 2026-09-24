<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Ledger;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<JournalLine> */
class JournalLineFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $ledger = Ledger::factory()->create();

        return [
            'journal_entry_id' => JournalEntry::factory()->for($ledger),
            'account_id' => Account::factory()->for($ledger),
            'debit_minor' => 100,
            'credit_minor' => 0,
            'memo' => null,
        ];
    }
}
