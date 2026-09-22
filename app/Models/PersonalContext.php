<?php

namespace App\Models;

use App\ContextKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['context_id', 'actor_id'])]
class PersonalContext extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $binding): void {
            $context = Context::query()->findOrFail($binding->context_id);

            if ($context->kind !== ContextKind::Personal) {
                throw new LogicException('Personal Context binding requires a personal Context.');
            }
        });

        static::updating(function (): never {
            throw new LogicException('Personal Context bindings are immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException('Personal Context bindings are preserved.');
        });
    }

    /** @return BelongsTo<Context, $this> */
    public function context(): BelongsTo
    {
        return $this->belongsTo(Context::class);
    }

    /** @return BelongsTo<Actor, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(Actor::class);
    }
}
