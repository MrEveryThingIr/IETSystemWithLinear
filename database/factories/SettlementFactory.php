<?php

namespace Database\Factories;

use App\Models\Actor;
use App\Models\FinancialObligation;
use App\Models\Settlement;
use App\SettlementStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Settlement> */
class SettlementFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'financial_obligation_id' => FinancialObligation::factory(),
            'amount_minor' => 500,
            'paid_at' => now(),
            'method' => 'cash',
            'reference' => null,
            'note' => null,
            'proposed_by_actor_id' => Actor::factory(),
            'status' => SettlementStatus::PendingConfirmation,
            'confirmed_by_actor_id' => null,
            'confirmed_at' => null,
            'rejected_by_actor_id' => null,
            'rejection_note' => null,
            'rejected_at' => null,
        ];
    }
}
