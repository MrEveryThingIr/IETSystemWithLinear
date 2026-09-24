<?php

namespace Database\Factories;

use App\Models\Actor;
use App\Models\Plan;
use App\Models\PlanScheduleRule;
use App\PlanScheduleFrequency;
use App\PlanScheduleRuleStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PlanScheduleRule> */
class PlanScheduleRuleFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'plan_id' => Plan::factory(),
            'created_by_actor_id' => Actor::factory(),
            'frequency' => PlanScheduleFrequency::Once,
            'interval' => 1,
            'starts_on' => now()->toDateString(),
            'start_time' => '09:00:00',
            'duration_minutes' => 60,
            'weekdays' => null,
            'selected_dates' => null,
            'ends_on' => null,
            'occurrence_limit' => 1,
            'window_before_minutes' => 0,
            'window_after_minutes' => 0,
            'timezone' => 'UTC',
            'status' => PlanScheduleRuleStatus::Active,
        ];
    }
}
