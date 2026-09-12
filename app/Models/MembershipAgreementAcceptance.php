<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $group_agreement_id
 * @property int $version_number
 * @property int $represented_actor_id
 * @property int|null $acting_user_id
 * @property string $hash_algorithm
 * @property CarbonImmutable|null $version_effective_from
 * @property CarbonImmutable|null $version_effective_until
 * @property bool $required_for_admission
 * @property bool $reacceptance_required
 */
#[Fillable(['source_admission_acceptance_id', 'group_membership_id', 'group_membership_event_id', 'group_agreement_version_id', 'group_agreement_id', 'version_number', 'accepted_by_actor_id', 'represented_actor_id', 'acting_user_id', 'accepted_at', 'evidence_hash', 'hash_algorithm', 'version_effective_from', 'version_effective_until', 'required_for_admission', 'reacceptance_required', 'evidence_schema_version'])]
class MembershipAgreementAcceptance extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'accepted_at' => 'immutable_datetime',
            'version_effective_from' => 'immutable_datetime',
            'version_effective_until' => 'immutable_datetime',
            'required_for_admission' => 'boolean',
            'reacceptance_required' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => abort(422, 'Membership acceptance evidence is immutable.'));
        static::deleting(fn (): never => abort(422, 'Membership acceptance evidence is immutable.'));
    }

    /** @return BelongsTo<GroupMembership, $this> */
    public function membership(): BelongsTo
    {
        return $this->belongsTo(GroupMembership::class, 'group_membership_id');
    }

    public function sourceAdmissionAcceptance(): BelongsTo
    {
        return $this->belongsTo(AgreementAcceptance::class, 'source_admission_acceptance_id');
    }

    /** @return BelongsTo<GroupMembershipEvent, $this> */
    public function membershipEvent(): BelongsTo
    {
        return $this->belongsTo(GroupMembershipEvent::class, 'group_membership_event_id');
    }

    /** @return BelongsTo<GroupAgreementVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(GroupAgreementVersion::class, 'group_agreement_version_id');
    }

    public function agreement(): BelongsTo
    {
        return $this->belongsTo(GroupAgreement::class, 'group_agreement_id');
    }

    /** @return BelongsTo<Actor, $this> */
    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'accepted_by_actor_id');
    }

    public function representedActor(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'represented_actor_id');
    }

    public function actingUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acting_user_id');
    }
}
