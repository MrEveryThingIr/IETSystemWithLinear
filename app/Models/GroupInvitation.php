<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupInvitation extends Model
{
    use HasFactory;

    /** @return BelongsTo<Group, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /** @return BelongsTo<Actor, $this> */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'invited_by_actor_id');
    }

    /** @return BelongsTo<Actor, $this> */
    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'accepted_by_actor_id');
    }
}
