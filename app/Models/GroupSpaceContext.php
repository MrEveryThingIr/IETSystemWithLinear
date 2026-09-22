<?php

namespace App\Models;

use App\ContextKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['context_id', 'group_space_id'])]
class GroupSpaceContext extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $binding): void {
            $context = Context::query()->findOrFail($binding->context_id);

            if ($context->kind !== ContextKind::GroupSpace) {
                throw new LogicException('GroupSpace Context binding requires a GroupSpace Context.');
            }
        });

        static::updating(function (): never {
            throw new LogicException('GroupSpace Context bindings are immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException('GroupSpace Context bindings are preserved.');
        });
    }

    /** @return BelongsTo<Context, $this> */
    public function context(): BelongsTo
    {
        return $this->belongsTo(Context::class);
    }

    /** @return BelongsTo<GroupSpace, $this> */
    public function groupSpace(): BelongsTo
    {
        return $this->belongsTo(GroupSpace::class);
    }
}
