<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['group_space_id', 'author_actor_id', 'body'])]
class GroupSpaceMessage extends Model
{
    use HasFactory;

    /** @return BelongsTo<GroupSpace, $this> */
    public function space(): BelongsTo
    {
        return $this->belongsTo(GroupSpace::class, 'group_space_id');
    }

    /** @return BelongsTo<Actor, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'author_actor_id');
    }
}
