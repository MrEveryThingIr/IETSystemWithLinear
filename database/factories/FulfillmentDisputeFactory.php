<?php

namespace Database\Factories;

use App\FulfillmentDisputeStatus;
use App\FulfillmentStatus;
use App\Models\Actor;
use App\Models\Fulfillment;
use App\Models\FulfillmentDispute;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FulfillmentDispute> */
class FulfillmentDisputeFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'fulfillment_id' => Fulfillment::factory(),
            'opened_by_actor_id' => Actor::factory(),
            'original_status' => FulfillmentStatus::Rejected,
            'reason' => fake()->sentence(),
            'status' => FulfillmentDisputeStatus::Open,
            'resolved_by_actor_id' => null,
            'resolution_status' => null,
            'resolution_note' => null,
            'opened_at' => now(),
            'resolved_at' => null,
        ];
    }
}
