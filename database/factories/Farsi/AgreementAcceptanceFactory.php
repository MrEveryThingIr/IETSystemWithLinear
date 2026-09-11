<?php

namespace Database\Factories\Farsi;

use App\Models\AgreementAcceptance;
use Database\Factories\AgreementAcceptanceFactory as BaseAgreementAcceptanceFactory;

class AgreementAcceptanceFactory extends BaseAgreementAcceptanceFactory
{
    protected $model = AgreementAcceptance::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'admission_id' => AdmissionFactory::new()->approved(),
            'group_agreement_version_id' => GroupAgreementVersionFactory::new()->active(),
            'accepted_by_actor_id' => ActorFactory::new(),
            'accepted_at' => now(),
            'evidence_hash' => hash('sha256', fake('fa_IR')->realText(100)),
        ];
    }
}
