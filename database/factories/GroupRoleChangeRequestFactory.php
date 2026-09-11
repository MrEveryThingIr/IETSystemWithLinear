<?php

namespace Database\Factories;

use App\Models\Actor;
use App\Models\GroupMembership;
use App\Models\GroupRoleChangeRequest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Spatie\Permission\Models\Role;

/**
 * @extends Factory<GroupRoleChangeRequest>
 */
class GroupRoleChangeRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'membership_id' => GroupMembership::factory(),
            'group_id' => fn (array $attributes): int => GroupMembership::query()->findOrFail($attributes['membership_id'])->group_id,
            'requested_role_id' => fn (array $attributes): int => Role::query()->create([
                'group_id' => $attributes['group_id'],
                'name' => 'Requested role '.fake()->unique()->numerify('#####'),
                'guard_name' => 'web',
            ])->id,
            'request_type' => 'grant',
            'pending_key' => fake()->unique()->uuid(),
            'status' => 'pending',
            'reviewed_by_actor_id' => null,
            'reviewed_at' => null,
            'review_locked_at' => null,
        ];
    }

    public function approved(?Actor $reviewer = null): static
    {
        return $this->state(fn (): array => [
            'status' => 'approved',
            'pending_key' => null,
            'reviewed_by_actor_id' => $reviewer?->id ?? Actor::factory(),
            'reviewed_at' => now(),
            'review_locked_at' => now(),
        ]);
    }

    public function rejected(?Actor $reviewer = null): static
    {
        return $this->state(fn (): array => [
            'status' => 'rejected',
            'pending_key' => null,
            'reviewed_by_actor_id' => $reviewer?->id ?? Actor::factory(),
            'reviewed_at' => now(),
            'review_locked_at' => now(),
        ]);
    }

    public function revoke(): static
    {
        return $this->state(fn (): array => ['request_type' => 'revoke']);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => ['status' => 'cancelled', 'pending_key' => null]);
    }
}
