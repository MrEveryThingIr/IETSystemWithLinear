<?php

namespace Database\Factories;

use App\Models\Actor;
use App\Models\Plan;
use App\Models\PlanParticipant;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PlanParticipant> */
class PlanParticipantFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'plan_id' => Plan::factory(),
            'actor_id' => Actor::factory(),
            'assigned_by_actor_id' => Actor::factory(),
            'role' => 'participant',
            'status' => 'active',
        ];
    }
}
