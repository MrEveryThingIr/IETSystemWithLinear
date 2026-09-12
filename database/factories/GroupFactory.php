<?php

namespace Database\Factories;

use App\Models\Actor;
use App\Models\Group;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Group>
 */
class GroupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->city().' Construction Project',
            'description' => fake()->paragraphs(2, true),
            'timezone' => 'UTC',
            'created_by_actor_id' => Actor::factory(),
        ];
    }
}
