<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['blocker_actor_id', 'blocked_actor_id'])]
class ActorBlock extends Model
{
    /** Whether either person has blocked the other. */
    public static function between(int $a, int $b): bool
    {
        return self::query()
            ->where(fn ($q) => $q->where('blocker_actor_id', $a)->where('blocked_actor_id', $b))
            ->orWhere(fn ($q) => $q->where('blocker_actor_id', $b)->where('blocked_actor_id', $a))
            ->exists();
    }

    /** @return BelongsTo<Actor, $this> */
    public function blocked(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'blocked_actor_id');
    }
}
