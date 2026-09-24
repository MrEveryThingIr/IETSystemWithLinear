<?php

namespace Database\Factories;

use App\Models\Actor;
use App\Models\Concept;
use App\Models\Relationship;
use App\RelationshipStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Relationship> */
class RelationshipFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'purpose_concept_id' => Concept::factory(),
            'originating_intent_id' => null,
            'created_by_actor_id' => Actor::factory(),
            'status' => RelationshipStatus::Proposed,
            'metadata' => [],
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => RelationshipStatus::Active,
            'activated_at' => now(),
        ]);
    }

    public function ended(): static
    {
        return $this->state(fn (): array => [
            'status' => RelationshipStatus::Ended,
            'activated_at' => now()->subDay(),
            'ended_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => [
            'status' => RelationshipStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }
}
