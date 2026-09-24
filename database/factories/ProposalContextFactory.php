<?php

namespace Database\Factories;

use App\ContextKind;
use App\Models\Context;
use App\Models\Proposal;
use App\Models\ProposalContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProposalContext> */
class ProposalContextFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'context_id' => Context::factory()->state(['kind' => ContextKind::Negotiation]),
            'proposal_id' => Proposal::factory(),
        ];
    }
}
