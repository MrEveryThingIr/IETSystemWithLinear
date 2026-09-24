<?php

namespace Database\Factories;

use App\Models\Actor;
use App\Models\Proposal;
use App\Models\ProposalParty;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProposalParty> */
class ProposalPartyFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'proposal_id' => Proposal::factory(),
            'actor_id' => Actor::factory(),
            'role' => 'party',
            'required' => true,
            'added_by_actor_id' => Actor::factory(),
        ];
    }
}
