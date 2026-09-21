<?php

namespace Database\Factories;

use App\Models\Actor;
use App\Models\ActorProfile;
use App\Models\ActorProfileDisclosureGrant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ActorProfileDisclosureGrant> */
class ActorProfileDisclosureGrantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'actor_profile_id' => ActorProfile::factory(),
            'grantee_actor_id' => Actor::factory(),
            'purpose' => fake()->sentence(4),
            'expires_at' => now()->addDays(30),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (ActorProfileDisclosureGrant $grant): void {
            $profile = ActorProfile::query()->findOrFail($grant->getAttribute('actor_profile_id'));

            $grant->setAttribute('created_by_actor_id', $profile->actor_id);
        });
    }

    public function revoked(): static
    {
        return $this->state(fn (): array => ['revoked_at' => now()]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => ['expires_at' => now()->subMinute()]);
    }
}
