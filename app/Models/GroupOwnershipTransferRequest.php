<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['group_id', 'from_membership_id', 'to_membership_id', 'status', 'pending_group_id', 'responded_at'])]
class GroupOwnershipTransferRequest extends Model
{
    protected function casts(): array
    {
        return ['responded_at' => 'datetime'];
    }

    /** @return BelongsTo<Group, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /** @return BelongsTo<GroupMembership, $this> */
    public function sourceMembership(): BelongsTo
    {
        return $this->belongsTo(GroupMembership::class, 'from_membership_id');
    }

    /** @return BelongsTo<GroupMembership, $this> */
    public function targetMembership(): BelongsTo
    {
        return $this->belongsTo(GroupMembership::class, 'to_membership_id');
    }
}
