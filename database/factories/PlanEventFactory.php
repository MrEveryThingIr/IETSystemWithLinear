<?php

namespace Database\Factories;

use App\Models\Actor;
use App\Models\Plan;
use App\Models\PlanEvent;
use App\PlanEventType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PlanEvent> */
class PlanEventFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'plan_id' => Plan::factory(),
            'actor_id' => Actor::factory(),
            'event_type' => PlanEventType::Created,
            'payload' => [],
            'created_at' => now(),
        ];
    }
}
