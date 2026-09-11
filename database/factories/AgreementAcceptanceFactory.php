<?php

namespace Database\Factories;

use App\Models\Actor;
use App\Models\Admission;
use App\Models\AgreementAcceptance;
use App\Models\GroupAgreementVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgreementAcceptance>
 */
class AgreementAcceptanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'admission_id' => Admission::factory()->approved(),
            'group_agreement_version_id' => GroupAgreementVersion::factory()->active(),
            'accepted_by_actor_id' => Actor::factory(),
            'accepted_at' => now(),
            'evidence_hash' => hash('sha256', fake()->paragraph()),
        ];
    }

    public function forEvidence(Admission $admission, GroupAgreementVersion $version): static
    {
        return $this->state(fn (): array => [
            'admission_id' => $admission->id,
            'group_agreement_version_id' => $version->id,
            'accepted_by_actor_id' => $admission->candidate_actor_id,
            'evidence_hash' => hash('sha256', $version->content),
        ]);
    }
}
