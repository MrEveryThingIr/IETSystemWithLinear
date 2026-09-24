<?php

namespace Database\Factories;

use App\Models\Actor;
use App\Models\Context;
use App\Models\Plan;
use App\PlanStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Plan> */
class PlanFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'context_id' => Context::factory(),
            'created_by_actor_id' => Actor::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->sentence(),
            'timezone' => 'UTC',
            'status' => PlanStatus::Active,
            'origin_type' => null,
            'origin_uuid' => null,
            'metadata' => [],
        ];
    }
}
