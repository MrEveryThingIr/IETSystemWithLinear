<?php

namespace App\Models;

use Database\Factories\GroupMembershipEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class GroupMembershipEvent extends Model
{
    /** @use HasFactory<GroupMembershipEventFactory> */
    use HasFactory;

    public $timestamps = false;

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Membership events are immutable.');
        });
        static::deleting(function (): never {
            throw new LogicException('Membership events are immutable.');
        });
    }

    /** @return BelongsTo<GroupMembership, $this> */
    public function membership(): BelongsTo
    {
        return $this->belongsTo(GroupMembership::class, 'group_membership_id');
    }

    /** @return BelongsTo<Group, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /** @return BelongsTo<Actor, $this> */
    public function actingActor(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'acting_actor_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }
}
