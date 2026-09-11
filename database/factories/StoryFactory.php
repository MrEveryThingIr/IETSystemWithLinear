<?php

namespace Database\Factories;

use App\Models\Actor;
use App\Models\Group;
use App\Models\Story;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Story>
 */
class StoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_id' => Group::factory(),
            'created_by_actor_id' => Actor::factory(),
            'title' => fake()->sentence(5),
            'body' => fake()->paragraphs(4, true),
        ];
    }
}
