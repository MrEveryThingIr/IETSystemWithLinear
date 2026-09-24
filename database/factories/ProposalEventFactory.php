<?php

namespace Database\Factories;

use App\Models\Actor;
use App\Models\Proposal;
use App\Models\ProposalEvent;
use App\ProposalEventType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProposalEvent> */
class ProposalEventFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'proposal_id' => Proposal::factory(),
            'proposal_version_id' => null,
            'actor_id' => Actor::factory(),
            'event_type' => ProposalEventType::Created,
            'payload' => [],
        ];
    }
}
