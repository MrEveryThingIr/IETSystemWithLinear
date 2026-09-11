<?php

namespace Database\Factories\Farsi;

use App\Models\MembershipAgreementAcceptance;
use Database\Factories\MembershipAgreementAcceptanceFactory as BaseMembershipAgreementAcceptanceFactory;

class MembershipAgreementAcceptanceFactory extends BaseMembershipAgreementAcceptanceFactory
{
    protected $model = MembershipAgreementAcceptance::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_membership_id' => GroupMembershipFactory::new(),
            'group_agreement_version_id' => GroupAgreementVersionFactory::new()->active(),
            'accepted_by_actor_id' => ActorFactory::new(),
            'accepted_at' => now(),
            'evidence_hash' => hash('sha256', fake('fa_IR')->realText(100)),
        ];
    }
}
