<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Binds a Context of kind "direct" to exactly two people (lower actor id first). */
#[Fillable(['context_id', 'actor_one_id', 'actor_two_id', 'created_by_actor_id'])]
class DirectContext extends Model
{
    protected static function booted(): void
    {
        static::creating(function (self $binding): void {
            if ((int) $binding->actor_one_id >= (int) $binding->actor_two_id) {
                throw new LogicException('A direct conversation has two different people, lower id first.');
            }
        });
        static::updating(function (): never {
            throw new LogicException('Who a direct conversation is between never changes.');
        });
    }

    /** @return array{0: int, 1: int} */
    public static function pair(int $a, int $b): array
    {
        return $a < $b ? [$a, $b] : [$b, $a];
    }

    public function includes(int $actorId): bool
    {
        return in_array($actorId, [(int) $this->actor_one_id, (int) $this->actor_two_id], true);
    }

    public function otherThan(int $actorId): int
    {
        return (int) $this->actor_one_id === $actorId ? (int) $this->actor_two_id : (int) $this->actor_one_id;
    }

    /** @return BelongsTo<Context, $this> */
    public function context(): BelongsTo
    {
        return $this->belongsTo(Context::class);
    }

    /** @return BelongsTo<Actor, $this> */
    public function actorOne(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'actor_one_id');
    }

    /** @return BelongsTo<Actor, $this> */
    public function actorTwo(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'actor_two_id');
    }
}
