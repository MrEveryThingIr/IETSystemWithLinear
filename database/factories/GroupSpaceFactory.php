<?php

namespace Database\Factories;

use App\Models\Actor;
use App\Models\Group;
use App\Models\GroupSpace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<GroupSpace>
 */
class GroupSpaceFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'group_id' => Group::factory(),
            'created_by_actor_id' => Actor::factory(),
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'kind' => 'chat',
            'status' => 'active',
            'is_default' => false,
        ];
    }
}
