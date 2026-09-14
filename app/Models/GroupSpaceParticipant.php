<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['group_space_id', 'actor_id', 'access', 'role', 'granted_by_actor_id'])]
class GroupSpaceParticipant extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (GroupSpaceParticipant $participant): void {
            if (! in_array($participant->access, ['allow', 'deny'], true)) {
                throw new LogicException('Space participant access must be allow or deny.');
            }

            if (! in_array($participant->role, ['participant', 'manager'], true)) {
                throw new LogicException('Space participant role must be participant or manager.');
            }

            if ($participant->role === 'manager' && $participant->access !== 'allow') {
                throw new LogicException('A Space manager must have explicit allow access.');
            }
        });
    }

    /** @return BelongsTo<GroupSpace, $this> */
    public function space(): BelongsTo
    {
        return $this->belongsTo(GroupSpace::class, 'group_space_id');
    }

    /** @return BelongsTo<Actor, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(Actor::class);
    }

    /** @return BelongsTo<Actor, $this> */
    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'granted_by_actor_id');
    }
}
