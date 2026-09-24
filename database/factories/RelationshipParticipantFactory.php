<?php

namespace Database\Factories;

use App\Models\Actor;
use App\Models\Relationship;
use App\Models\RelationshipParticipant;
use App\RelationshipParticipantStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RelationshipParticipant> */
class RelationshipParticipantFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'relationship_id' => Relationship::factory(),
            'actor_id' => Actor::factory(),
            'role' => 'collaborator',
            'status' => RelationshipParticipantStatus::Invited,
            'can_manage' => false,
            'invited_by_actor_id' => Actor::factory(),
            'invited_at' => now(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => RelationshipParticipantStatus::Active,
            'joined_at' => now(),
        ]);
    }

    public function manager(): static
    {
        return $this->active()->state(fn (): array => ['can_manage' => true]);
    }

    public function declined(): static
    {
        return $this->state(fn (): array => [
            'status' => RelationshipParticipantStatus::Declined,
            'declined_at' => now(),
        ]);
    }
}
