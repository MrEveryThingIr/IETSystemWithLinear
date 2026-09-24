<?php

namespace App\Models;

use App\ContextKind;
use Database\Factories\RelationshipContextFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['context_id', 'relationship_id'])]
class RelationshipContext extends Model
{
    /** @use HasFactory<RelationshipContextFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $binding): void {
            $context = Context::query()->findOrFail($binding->context_id);

            if ($context->kind !== ContextKind::Relationship) {
                throw new LogicException('Relationship Context binding requires a relationship Context.');
            }
        });

        static::updating(function (): never {
            throw new LogicException('Relationship Context bindings are immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException('Relationship Context bindings are preserved.');
        });
    }

    /** @return BelongsTo<Context, $this> */
    public function context(): BelongsTo
    {
        return $this->belongsTo(Context::class);
    }

    /** @return BelongsTo<Relationship, $this> */
    public function relationship(): BelongsTo
    {
        return $this->belongsTo(Relationship::class);
    }
}
