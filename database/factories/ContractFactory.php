<?php

namespace Database\Factories;

use App\ContractStatus;
use App\Models\Actor;
use App\Models\Contract;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Contract> */
class ContractFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'relationship_id' => null,
            'source_proposal_version_id' => null,
            'created_by_actor_id' => Actor::factory(),
            'status' => ContractStatus::Pending,
        ];
    }
}
