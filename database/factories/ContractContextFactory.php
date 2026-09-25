<?php

namespace Database\Factories;

use App\ContextKind;
use App\Models\Context;
use App\Models\Contract;
use App\Models\ContractContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ContractContext> */
class ContractContextFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'context_id' => Context::factory()->state(['kind' => ContextKind::Contract]),
            'contract_id' => Contract::factory(),
        ];
    }
}
