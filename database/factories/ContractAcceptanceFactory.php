<?php

namespace Database\Factories;

use App\Models\ContractAcceptance;
use App\Models\ContractVersionParty;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ContractAcceptance> */
class ContractAcceptanceFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'contract_version_party_id' => ContractVersionParty::factory(),
            'accepted_by_user_id' => User::factory(),
            'accepted_at' => now(),
        ];
    }
}
