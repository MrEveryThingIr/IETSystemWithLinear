<?php

namespace Database\Factories;

use App\Models\Actor;
use App\Models\Responsibility;
use App\Models\Story;
use App\Models\StoryRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StoryRole>
 */
class StoryRoleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'story_id' => Story::factory(),
            'actor_id' => Actor::factory(),
            'responsibility' => fake()->randomElement(Responsibility::values()),
        ];
    }
}
