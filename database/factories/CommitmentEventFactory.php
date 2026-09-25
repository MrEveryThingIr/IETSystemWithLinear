<?php

namespace Database\Factories;

use App\CommitmentEventType;
use App\Models\Actor;
use App\Models\Commitment;
use App\Models\CommitmentEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CommitmentEvent> */
class CommitmentEventFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'commitment_id' => Commitment::factory(),
            'actor_id' => Actor::factory(),
            'event_type' => CommitmentEventType::Created,
            'payload' => null,
        ];
    }
}
