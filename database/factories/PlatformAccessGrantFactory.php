<?php

namespace Database\Factories;

use App\Models\PlatformAccessGrant;
use App\Models\User;
use App\PlatformRole;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformAccessGrant>
 */
class PlatformAccessGrantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'role' => PlatformRole::Superadmin,
            'granted_by_user_id' => null,
            'granted_at' => now(),
            'revoked_by_user_id' => null,
            'revoked_at' => null,
            'reason' => 'Platform administration test grant.',
            'correlation_id' => fake()->unique()->uuid(),
        ];
    }

    public function revoked(?User $administrator = null): static
    {
        return $this->state(fn (): array => [
            'revoked_by_user_id' => $administrator?->id ?? User::factory(),
            'revoked_at' => now(),
        ]);
    }
}
