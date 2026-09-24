<?php

namespace Database\Factories;

use App\Models\PlanOccurrence;
use App\Models\PlanScheduleRule;
use App\PlanOccurrenceStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PlanOccurrence> */
class PlanOccurrenceFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'schedule_rule_id' => PlanScheduleRule::factory(),
            'plan_id' => function (array $attributes): int {
                return PlanScheduleRule::query()->findOrFail($attributes['schedule_rule_id'])->plan_id;
            },
            'local_date' => now()->toDateString(),
            'scheduled_start_at' => now()->addHour(),
            'scheduled_end_at' => now()->addHours(2),
            'window_start_at' => now()->addHour(),
            'window_end_at' => now()->addHours(2),
            'timezone' => 'UTC',
            'status' => PlanOccurrenceStatus::Scheduled,
            'actual_start_at' => null,
            'actual_end_at' => null,
            'completed_at' => null,
            'origin_type' => null,
            'origin_uuid' => null,
            'metadata' => [],
        ];
    }
}
