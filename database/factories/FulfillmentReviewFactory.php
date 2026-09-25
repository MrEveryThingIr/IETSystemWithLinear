<?php

namespace Database\Factories;

use App\FulfillmentReviewDecision;
use App\Models\Actor;
use App\Models\Fulfillment;
use App\Models\FulfillmentReview;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FulfillmentReview> */
class FulfillmentReviewFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'fulfillment_id' => Fulfillment::factory(),
            'reviewer_actor_id' => Actor::factory(),
            'decision' => FulfillmentReviewDecision::Accepted,
            'note' => null,
            'reviewed_at' => now(),
        ];
    }
}
