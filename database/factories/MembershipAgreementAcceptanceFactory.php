<?php

namespace Database\Factories;

use App\Models\Actor;
use App\Models\GroupAgreementVersion;
use App\Models\GroupMembership;
use App\Models\GroupMembershipEvent;
use App\Models\MembershipAgreementAcceptance;
use App\Support\AgreementEvidence;
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
            'group_membership_event_id' => function (array $attributes): int {
                $membership = GroupMembership::query()->findOrFail($attributes['group_membership_id']);

                return $this->membershipEventId($membership);
            },
            'group_agreement_version_id' => GroupAgreementVersion::factory()->active(),
            'accepted_by_actor_id' => Actor::factory(),
            'represented_actor_id' => fn (array $attributes): int => $attributes['accepted_by_actor_id'],
            'acting_user_id' => fn (array $attributes): ?int => Actor::query()->findOrFail($attributes['accepted_by_actor_id'])->user_id,
            'group_agreement_id' => fn (array $attributes): int => GroupAgreementVersion::query()->findOrFail($attributes['group_agreement_version_id'])->group_agreement_id,
            'version_number' => fn (array $attributes): int => GroupAgreementVersion::query()->findOrFail($attributes['group_agreement_version_id'])->version,
            'accepted_at' => now(),
            'evidence_hash' => fn (array $attributes): string => GroupAgreementVersion::query()->findOrFail($attributes['group_agreement_version_id'])->content_hash,
            'hash_algorithm' => AgreementEvidence::HASH_ALGORITHM,
            'version_effective_from' => fn (array $attributes) => GroupAgreementVersion::query()->findOrFail($attributes['group_agreement_version_id'])->effective_from,
            'version_effective_until' => fn (array $attributes) => GroupAgreementVersion::query()->findOrFail($attributes['group_agreement_version_id'])->effective_until,
            'required_for_admission' => fn (array $attributes): bool => GroupAgreementVersion::query()->findOrFail($attributes['group_agreement_version_id'])->agreement()->value('required_for_admission'),
            'reacceptance_required' => fn (array $attributes): bool => GroupAgreementVersion::query()->findOrFail($attributes['group_agreement_version_id'])->reacceptance_required,
            'evidence_schema_version' => AgreementEvidence::SCHEMA_VERSION,
        ];
    }

    public function forEvidence(GroupMembership $membership, GroupAgreementVersion $version): static
    {
        return $this->state(fn (): array => [
            'group_membership_id' => $membership->id,
            'group_membership_event_id' => $this->membershipEventId($membership),
            'group_agreement_version_id' => $version->id,
            ...AgreementEvidence::forAcceptance($version, $membership->actor),
        ]);
    }

    private function membershipEventId(GroupMembership $membership): int
    {
        return $membership->currentParticipationEvent()?->id
            ?? GroupMembershipEvent::factory()->create([
                'group_membership_id' => $membership->id,
                'group_id' => $membership->group_id,
                'acting_actor_id' => null,
                'event' => 'membership.activated',
                'from_status' => null,
                'to_status' => 'active',
            ])->id;
    }
}
