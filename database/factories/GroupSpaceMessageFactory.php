<?php

namespace Database\Factories;

use App\Models\Actor;
use App\Models\GroupSpace;
use App\Models\GroupSpaceMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GroupSpaceMessage>
 */
class GroupSpaceMessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'group_space_id' => GroupSpace::factory(),
            'author_actor_id' => Actor::factory(),
            'body' => fake()->paragraph(),
        ];
    }
}
