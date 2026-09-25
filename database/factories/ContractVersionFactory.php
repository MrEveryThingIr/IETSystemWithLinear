<?php

namespace Database\Factories;

use App\ContractVersionStatus;
use App\Models\Actor;
use App\Models\Contract;
use App\Models\ContractVersion;
use App\Models\SpaceContentRevision;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ContractVersion> */
class ContractVersionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'contract_id' => Contract::factory(),
            'version' => 1,
            'terms_content_revision_id' => SpaceContentRevision::factory(),
            'supersedes_version_id' => null,
            'proposed_by_actor_id' => Actor::factory(),
            'status' => ContractVersionStatus::Proposed,
            'effective_from' => now(),
            'effective_timezone' => 'UTC',
            'effective_until' => null,
            'note' => null,
            'proposed_at' => now(),
            'accepted_at' => null,
            'activated_at' => null,
            'superseded_at' => null,
        ];
    }
}
