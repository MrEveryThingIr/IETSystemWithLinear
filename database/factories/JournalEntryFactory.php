<?php

namespace Database\Factories;

use App\JournalEntryKind;
use App\Models\Actor;
use App\Models\JournalEntry;
use App\Models\Ledger;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<JournalEntry> */
class JournalEntryFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'ledger_id' => Ledger::factory(),
            'kind' => JournalEntryKind::Expense,
            'occurred_on' => now()->toDateString(),
            'description' => fake()->sentence(),
            'reverses_entry_id' => null,
            'correction_of_entry_id' => null,
            'created_by_actor_id' => Actor::factory(),
            'posted_at' => now(),
        ];
    }
}
