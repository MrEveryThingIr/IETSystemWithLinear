<?php

namespace Database\Factories;

use App\FinancialObligationEventType;
use App\Models\Actor;
use App\Models\FinancialObligation;
use App\Models\FinancialObligationEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FinancialObligationEvent> */
class FinancialObligationEventFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'financial_obligation_id' => FinancialObligation::factory(),
            'settlement_id' => null,
            'journal_entry_id' => null,
            'actor_id' => Actor::factory(),
            'event_type' => FinancialObligationEventType::Recognized,
            'payload' => null,
        ];
    }
}
