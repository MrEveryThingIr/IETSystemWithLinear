<?php

namespace Database\Factories;

use App\Models\Actor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Actor> */
class ActorFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['user_id' => User::factory()];
    }

    public function withoutUser(): static
    {
        return $this->state(fn (): array => ['user_id' => null]);
    }
}
