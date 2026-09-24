<?php

namespace Database\Factories;

use App\Models\Actor;
use App\Models\Relationship;
use App\Models\RelationshipEvent;
use App\RelationshipEventType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RelationshipEvent> */
class RelationshipEventFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'relationship_id' => Relationship::factory(),
            'actor_id' => Actor::factory(),
            'event_type' => RelationshipEventType::Proposed,
            'payload' => [],
        ];
    }
}
