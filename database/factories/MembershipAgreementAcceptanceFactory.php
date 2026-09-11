<?php

namespace Database\Factories;

use App\Models\Actor;
use App\Models\GroupAgreementVersion;
use App\Models\GroupMembership;
use App\Models\MembershipAgreementAcceptance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MembershipAgreementAcceptance>
 */
class MembershipAgreementAcceptanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_membership_id' => GroupMembership::factory(),
            'group_agreement_version_id' => GroupAgreementVersion::factory()->active(),
            'accepted_by_actor_id' => Actor::factory(),
            'accepted_at' => now(),
            'evidence_hash' => hash('sha256', fake()->paragraph()),
        ];
    }

    public function forEvidence(GroupMembership $membership, GroupAgreementVersion $version): static
    {
        return $this->state(fn (): array => [
            'group_membership_id' => $membership->id,
            'group_agreement_version_id' => $version->id,
            'accepted_by_actor_id' => $membership->actor_id,
            'evidence_hash' => hash('sha256', $version->content),
        ]);
    }
}
