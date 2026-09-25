<?php

namespace Database\Factories;

use App\FulfillmentStatus;
use App\Models\Actor;
use App\Models\Commitment;
use App\Models\Fulfillment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Fulfillment> */
class FulfillmentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'commitment_id' => Commitment::factory(),
            'plan_occurrence_id' => null,
            'corrects_fulfillment_id' => null,
            'submitted_by_actor_id' => Actor::factory(),
            'quantity' => '1.0000',
            'unit' => 'unit',
            'actual_start_at' => null,
            'actual_end_at' => null,
            'duration_minutes' => null,
            'notes' => null,
            'status' => FulfillmentStatus::Submitted,
            'submitted_at' => now(),
            'reviewed_at' => null,
        ];
    }
}
