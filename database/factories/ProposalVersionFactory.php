<?php

namespace Database\Factories;

use App\Models\Actor;
use App\Models\Proposal;
use App\Models\ProposalVersion;
use App\Models\SpaceContentRevision;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProposalVersion> */
class ProposalVersionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'proposal_id' => Proposal::factory(),
            'version' => 1,
            'terms_content_revision_id' => SpaceContentRevision::factory(),
            'proposed_by_actor_id' => Actor::factory(),
            'note' => null,
            'proposed_at' => now(),
        ];
    }
}
