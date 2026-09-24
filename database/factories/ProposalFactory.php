<?php

namespace Database\Factories;

use App\Models\Actor;
use App\Models\Proposal;
use App\ProposalStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Proposal> */
class ProposalFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'relationship_id' => null,
            'created_by_actor_id' => Actor::factory(),
            'status' => ProposalStatus::Negotiating,
        ];
    }
}
