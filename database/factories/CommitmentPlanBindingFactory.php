<?php

namespace Database\Factories;

use App\Models\Actor;
use App\Models\Commitment;
use App\Models\CommitmentPlanBinding;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CommitmentPlanBinding> */
class CommitmentPlanBindingFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'commitment_id' => Commitment::factory(),
            'plan_id' => Plan::factory(),
            'bound_by_actor_id' => Actor::factory(),
        ];
    }
}
