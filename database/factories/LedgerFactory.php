<?php

namespace Database\Factories;

use App\Models\Actor;
use App\Models\Context;
use App\Models\Ledger;
use App\Models\MonetaryUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Ledger> */
class LedgerFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'context_id' => Context::factory(),
            'monetary_unit_id' => MonetaryUnit::factory(),
            'key' => 'main',
            'name' => fake()->words(2, true),
            'status' => 'active',
            'created_by_actor_id' => Actor::factory(),
        ];
    }
}
