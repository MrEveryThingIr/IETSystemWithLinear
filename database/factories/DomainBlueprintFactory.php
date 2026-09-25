<?php

namespace Database\Factories;

use App\Models\DomainBlueprint;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DomainBlueprint> */
class DomainBlueprintFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(2),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'category' => 'test',
            'status' => DomainBlueprint::STATUS_ACTIVE,
            'current_version' => 0,
        ];
    }
}
