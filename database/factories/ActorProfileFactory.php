<?php

namespace Database\Factories;

use App\Models\Actor;
use App\Models\ActorProfile;
use App\ProfileVisibility;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ActorProfile> */
class ActorProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'actor_id' => Actor::factory(),
            'display_name' => fake()->name(),
            'headline' => fake()->jobTitle(),
            'bio' => fake()->paragraph(),
            'location_text' => fake()->city(),
            'website_url' => fake()->url(),
            'visibility' => ProfileVisibility::Private,
        ];
    }

    public function public(): static
    {
        return $this->state(fn (): array => ['visibility' => ProfileVisibility::Public]);
    }

    public function authenticated(): static
    {
        return $this->state(fn (): array => ['visibility' => ProfileVisibility::Authenticated]);
    }
}
