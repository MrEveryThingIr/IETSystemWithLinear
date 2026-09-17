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
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'group_id' => Group::factory(),
            'created_by_actor_id' => Actor::factory(),
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'kind' => 'chat',
            'access_mode' => 'group',
            'status' => 'active',
            'is_default' => false,
        ];
    }

    public function restricted(): static
    {
        return $this->state(fn (): array => ['access_mode' => 'restricted']);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => ['status' => 'archived']);
    }
}
