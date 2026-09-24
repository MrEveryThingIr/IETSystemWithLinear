<?php

namespace Database\Factories;

use App\ContractEventType;
use App\Models\Actor;
use App\Models\Contract;
use App\Models\ContractEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ContractEvent> */
class ContractEventFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'contract_id' => Contract::factory(),
            'contract_version_id' => null,
            'actor_id' => Actor::factory(),
            'event_type' => ContractEventType::Created,
            'payload' => null,
        ];
    }
}
