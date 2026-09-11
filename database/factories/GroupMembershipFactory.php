<?php

namespace Database\Factories;

use App\Models\Actor;
use App\Models\Group;
use App\Models\GroupMembership;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GroupMembership>
 */
class GroupMembershipFactory extends Factory
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
            'actor_id' => Actor::factory(),
            'role' => 'member',
            'status' => 'active',
        ];
    }

    public function removed(): static
    {
        return $this->state(fn (): array => ['status' => 'removed']);
    }
}
