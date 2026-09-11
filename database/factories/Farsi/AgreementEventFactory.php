<?php

namespace Database\Factories\Farsi;

use App\Models\AgreementEvent;
use Database\Factories\AgreementEventFactory as BaseAgreementEventFactory;

class AgreementEventFactory extends BaseAgreementEventFactory
{
    protected $model = AgreementEvent::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_agreement_id' => GroupAgreementFactory::new(),
            'group_agreement_version_id' => null,
            'actor_id' => ActorFactory::new(),
            'event' => 'agreement.version.created',
            'metadata' => null,
        ];
    }
}
