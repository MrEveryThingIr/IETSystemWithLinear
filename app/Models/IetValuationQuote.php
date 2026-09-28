<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'uuid',
    'usd_per_iet',
    'policy_version',
    'factors',
    'rationale',
    'effective_at',
    'published_by_user_id',
])]
class IetValuationQuote extends Model
{
    protected static function booted(): void
    {
        static::creating(function (self $quote): void {
            $quote->uuid ??= (string) Str::uuid();
        });

        static::updating(function (): never {
            throw new LogicException('Published IET valuation quotes are immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException('IET valuation history cannot be deleted.');
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<User, $this> */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by_user_id');
    }

    protected function casts(): array
    {
        return [
            'usd_per_iet' => 'decimal:18',
            'factors' => 'array',
            'effective_at' => 'immutable_datetime',
        ];
    }
}
