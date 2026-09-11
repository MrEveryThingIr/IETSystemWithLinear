<?php

namespace Database\Factories;

use App\Models\Actor;
use App\Models\AgreementEvent;
use App\Models\GroupAgreement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgreementEvent>
 */
class AgreementEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_agreement_id' => GroupAgreement::factory(),
            'group_agreement_version_id' => null,
            'actor_id' => Actor::factory(),
            'event' => 'agreement.version.created',
            'metadata' => null,
        ];
    }

    public function system(): static
    {
        return $this->state(fn (): array => ['actor_id' => null]);
    }
}
