<?php

namespace Database\Factories;

use App\Models\Actor;
use App\Models\ContractVersion;
use App\Models\ContractVersionParty;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ContractVersionParty> */
class ContractVersionPartyFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'contract_version_id' => ContractVersion::factory(),
            'actor_id' => Actor::factory(),
            'role' => 'party',
            'required' => true,
            'source_proposal_party_id' => null,
        ];
    }
}
