<?php

namespace Database\Factories;

use App\CommitmentKind;
use App\CommitmentStatus;
use App\Models\Actor;
use App\Models\Commitment;
use App\Models\ContractVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Commitment> */
class CommitmentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'contract_version_id' => ContractVersion::factory(),
            'created_by_actor_id' => Actor::factory(),
            'obligor_actor_id' => Actor::factory(),
            'beneficiary_actor_id' => Actor::factory(),
            'kind' => CommitmentKind::Work,
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->sentence(),
            'quantity' => '1.0000',
            'unit' => 'unit',
            'due_start_at' => null,
            'due_end_at' => null,
            'status' => CommitmentStatus::Active,
        ];
    }
}
