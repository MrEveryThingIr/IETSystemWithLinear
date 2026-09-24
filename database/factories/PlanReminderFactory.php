<?php

namespace Database\Factories;

use App\Models\Actor;
use App\Models\Plan;
use App\Models\PlanReminder;
use App\Models\PlanScheduleRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PlanReminder> */
class PlanReminderFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'plan_id' => Plan::factory(),
            'schedule_rule_id' => null,
            'created_by_actor_id' => Actor::factory(),
            'minutes_before' => 15,
            'channel' => 'app',
            'status' => 'active',
        ];
    }

    public function forRule(PlanScheduleRule $rule): static
    {
        return $this->state(fn (): array => [
            'plan_id' => $rule->plan_id,
            'schedule_rule_id' => $rule->id,
        ]);
    }
}
