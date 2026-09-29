<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['context_id', 'key', 'name'])]
class SystemContext extends Model
{
    protected static function booted(): void
    {
        static::creating(function (self $binding): void {
            $binding->key = strtolower(trim((string) $binding->key));
            $binding->name = trim((string) $binding->name);

            if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $binding->key)) {
                throw new LogicException('System Context key is invalid.');
            }

            if ($binding->name === '' || mb_strlen($binding->name) > 180) {
                throw new LogicException('System Context name is invalid.');
            }
        });

        static::updating(function (): never {
            throw new LogicException('System Context bindings are immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException('System Context bindings are durable and cannot be deleted directly.');
        });
    }

    /** @return BelongsTo<Context, $this> */
    public function context(): BelongsTo
    {
        return $this->belongsTo(Context::class);
    }
}
