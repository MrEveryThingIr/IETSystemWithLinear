<?php

namespace Database\Factories;

use App\Models\Actor;
use App\Models\PlanOccurrence;
use App\Models\PlanOccurrenceEvent;
use App\PlanOccurrenceEventType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PlanOccurrenceEvent> */
class PlanOccurrenceEventFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'plan_occurrence_id' => PlanOccurrence::factory(),
            'actor_id' => Actor::factory(),
            'event_type' => PlanOccurrenceEventType::Started,
            'payload' => [],
            'created_at' => now(),
        ];
    }
}
