<?php

namespace Database\Factories;

use App\Models\Actor;
use App\Models\GroupMembership;
use App\Models\GroupMembershipEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GroupMembershipEvent>
 */
class GroupMembershipEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_membership_id' => GroupMembership::factory(),
            'group_id' => fn (array $attributes): int => GroupMembership::query()->findOrFail($attributes['group_membership_id'])->group_id,
            'acting_actor_id' => Actor::factory(),
            'event' => 'membership.suspended',
            'from_status' => 'active',
            'to_status' => 'suspended',
            'reason' => fake()->sentence(),
            'metadata' => [],
            'created_at' => now(),
        ];
    }
}
