<?php

namespace Database\Factories;

use App\Models\MonetaryUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MonetaryUnit> */
class MonetaryUnitFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'name' => fake()->words(2, true),
            'symbol' => fake()->randomElement(['$', '€', '£', '¤']),
            'exponent' => 2,
        ];
    }
}
