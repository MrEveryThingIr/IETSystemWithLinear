<?php

namespace Database\Factories;

use App\Models\ProposalDecision;
use App\Models\ProposalParty;
use App\Models\ProposalVersion;
use App\Models\User;
use App\ProposalDecisionKind;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProposalDecision> */
class ProposalDecisionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'proposal_version_id' => ProposalVersion::factory(),
            'proposal_party_id' => ProposalParty::factory(),
            'decision' => ProposalDecisionKind::Accepted,
            'note' => null,
            'decided_by_user_id' => User::factory(),
            'decided_at' => now(),
        ];
    }
}
