<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable(['group_id', 'actor_id', 'status'])]
class GroupMembership extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(function (GroupMembership $membership): void {
            if ($membership->isDirty('status')) {
                throw new LogicException('Membership status changes require the lifecycle action.');
            }
        });
    }

    /** @return BelongsTo<Group, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /** @return BelongsTo<Actor, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(Actor::class);
    }

    /** @return HasMany<MembershipAgreementAcceptance, $this> */
    public function agreementAcceptances(): HasMany
    {
        return $this->hasMany(MembershipAgreementAcceptance::class);
    }

    /** @return HasMany<GroupMembershipEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(GroupMembershipEvent::class);
    }
}
