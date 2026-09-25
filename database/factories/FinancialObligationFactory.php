<?php

namespace Database\Factories;

use App\Models\Actor;
use App\Models\ContractVersion;
use App\Models\FinancialObligation;
use App\Models\Fulfillment;
use App\Models\MonetaryUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FinancialObligation> */
class FinancialObligationFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'fulfillment_id' => Fulfillment::factory(),
            'contract_version_id' => ContractVersion::factory(),
            'debtor_actor_id' => Actor::factory(),
            'creditor_actor_id' => Actor::factory(),
            'monetary_unit_id' => MonetaryUnit::factory(),
            'amount_minor' => 1000,
            'description' => fake()->sentence(),
            'due_at' => null,
            'recognized_by_actor_id' => Actor::factory(),
            'recognized_at' => now(),
        ];
    }
}
