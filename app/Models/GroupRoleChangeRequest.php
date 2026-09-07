<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['group_membership_id', 'requested_group_role_id', 'reason', 'status', 'reviewed_by_actor_id', 'reviewed_at'])]
class GroupRoleChangeRequest extends Model
{
    public function membership(): BelongsTo
    {
        return $this->belongsTo(GroupMembership::class, 'group_membership_id');
    }

    public function requestedRole(): BelongsTo
    {
        return $this->belongsTo(GroupRole::class, 'requested_group_role_id');
    }
}
