<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'uuid',
    'usd_pico_per_iet',
    'source',
    'factors',
    'note',
    'effective_at',
    'created_by_actor_id',
])]
class IetValuationSnapshot extends Model
{
    protected static function booted(): void
    {
        static::creating(function (self $snapshot): void {
            $snapshot->uuid ??= (string) Str::uuid();
        });

        static::updating(function (): never {
            throw new LogicException('IET valuation snapshots are immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException('IET valuation history cannot be deleted.');
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<Actor, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'created_by_actor_id');
    }

    /** @return HasMany<IetExchangeRequest, $this> */
    public function exchangeRequests(): HasMany
    {
        return $this->hasMany(IetExchangeRequest::class);
    }

    /** @return HasMany<Settlement, $this> */
    public function settlements(): HasMany
    {
        return $this->hasMany(Settlement::class);
    }

    protected function casts(): array
    {
        return [
            'usd_pico_per_iet' => 'integer',
            'factors' => 'array',
            'effective_at' => 'immutable_datetime',
        ];
    }
}
