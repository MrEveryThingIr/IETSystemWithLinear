<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['group_invitation_id', 'accepted_by_actor_id', 'accepted_at'])]
class GroupInvitationAcceptance extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['accepted_at' => 'datetime'];
    }

    /** @return BelongsTo<GroupInvitation, $this> */
    public function invitation(): BelongsTo
    {
        return $this->belongsTo(GroupInvitation::class, 'group_invitation_id');
    }

    /** @return BelongsTo<Actor, $this> */
    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'accepted_by_actor_id');
    }
}
