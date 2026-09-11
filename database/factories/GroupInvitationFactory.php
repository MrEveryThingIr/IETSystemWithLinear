<?php

namespace Database\Factories;

use App\Models\Actor;
use App\Models\Group;
use App\Models\GroupInvitation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<GroupInvitation>
 */
class GroupInvitationFactory extends Factory
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
            'invited_by_actor_id' => Actor::factory(),
            'email' => null,
            'content' => fake()->sentence(),
            'token' => Str::random(64),
            'expires_at' => now()->addWeeks(2),
            'max_uses' => 5,
            'uses_count' => 0,
            'revoked_at' => null,
        ];
    }

    public function targeted(?string $email = null): static
    {
        return $this->state(fn (): array => [
            'email' => $email ?? fake()->unique()->safeEmail(),
            'max_uses' => 1,
        ]);
    }

    public function unlimited(): static
    {
        return $this->state(fn (): array => ['max_uses' => null]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => ['expires_at' => now()->subDay()]);
    }

    public function revoked(): static
    {
        return $this->state(fn (): array => ['revoked_at' => now()->subHour()]);
    }

    public function exhausted(): static
    {
        return $this->state(fn (): array => ['max_uses' => 1, 'uses_count' => 1]);
    }
}
