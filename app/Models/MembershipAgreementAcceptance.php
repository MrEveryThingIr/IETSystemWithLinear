<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['group_membership_id', 'group_agreement_version_id', 'accepted_by_actor_id', 'accepted_at', 'evidence_hash'])]
class MembershipAgreementAcceptance extends Model
{
    protected function casts(): array
    {
        return ['accepted_at' => 'datetime'];
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

    /** @return BelongsTo<GroupAgreementVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(GroupAgreementVersion::class, 'group_agreement_version_id');
    }

    /** @return BelongsTo<Actor, $this> */
    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'accepted_by_actor_id');
    }
}
