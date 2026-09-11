<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role;

#[Fillable(['group_id', 'membership_id', 'requested_role_id', 'request_type', 'pending_key', 'status', 'reviewed_by_actor_id', 'reviewed_at'])]
class GroupRoleChangeRequest extends Model
{
    use HasFactory;

    /** @return BelongsTo<Group, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /** @return BelongsTo<GroupMembership, $this> */
    public function membership(): BelongsTo
    {
        return $this->belongsTo(GroupMembership::class);
    }

    /** @return BelongsTo<Role, $this> */
    public function requestedRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'requested_role_id');
    }

    /** @return BelongsTo<Actor, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'reviewed_by_actor_id');
    }
}
