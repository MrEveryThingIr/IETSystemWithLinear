<?php

namespace Database\Factories;

use App\AccountType;
use App\Models\Account;
use App\Models\Actor;
use App\Models\Ledger;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Account> */
class AccountFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'ledger_id' => Ledger::factory(),
            'code' => fake()->unique()->slug(2),
            'name' => fake()->words(2, true),
            'type' => AccountType::Asset,
            'system_key' => null,
            'status' => 'active',
            'created_by_actor_id' => Actor::factory(),
        ];
    }
}
