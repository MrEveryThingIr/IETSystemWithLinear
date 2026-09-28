<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'uuid',
    'sequence',
    'usd_numerator',
    'usd_denominator',
    'adjustment_ppm',
    'reason',
    'criteria',
    'effective_at',
    'created_by_user_id',
])]
class IetRateVersion extends Model
{
    protected static function booted(): void
    {
        static::creating(function (self $rate): void {
            $rate->uuid ??= (string) Str::uuid();
        });

        static::updating(function (): never {
            throw new LogicException('IET rate versions are immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException('IET rate history cannot be deleted.');
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'usd_numerator' => 'integer',
            'usd_denominator' => 'integer',
            'adjustment_ppm' => 'integer',
            'criteria' => 'array',
            'effective_at' => 'immutable_datetime',
        ];
    }
}
