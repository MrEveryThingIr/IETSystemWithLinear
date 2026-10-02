<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['context_id', 'business_id'])]
class BusinessContext extends Model
{
    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Business Context bindings are immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException('Business Context bindings are durable and cannot be deleted directly.');
        });
    }

    /** @return BelongsTo<Context, $this> */
    public function context(): BelongsTo
    {
        return $this->belongsTo(Context::class);
    }

    /** @return BelongsTo<Business, $this> */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
