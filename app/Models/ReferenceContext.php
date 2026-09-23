<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['context_id', 'key', 'audience', 'managed_by_actor_id'])]
class ReferenceContext extends Model
{
    public const AUDIENCE_AUTHENTICATED = 'authenticated';

    protected static function booted(): void
    {
        static::creating(function (self $reference): void {
            $reference->key = strtolower(trim($reference->key));
            $reference->audience = strtolower(trim($reference->audience));

            if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $reference->key)) {
                throw new LogicException('Reference Context key is invalid.');
            }

            if ($reference->audience !== self::AUDIENCE_AUTHENTICATED) {
                throw new LogicException('Reference Context audience is invalid.');
            }
        });

        static::updating(function (): never {
            throw new LogicException('Reference Context bindings are immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException('Reference Context bindings are durable and cannot be deleted directly.');
        });
    }

    /** @return BelongsTo<Context, $this> */
    public function context(): BelongsTo
    {
        return $this->belongsTo(Context::class);
    }

    /** @return BelongsTo<Actor, $this> */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'managed_by_actor_id');
    }
}
