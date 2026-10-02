<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'uuid',
    'business_listing_id',
    'business_listing_version_id',
    'monetary_unit_id',
    'price_type',
    'amount_minor',
    'basis',
    'visibility',
    'valid_from',
    'valid_until',
    'created_by_actor_id',
    'reason',
])]
class BusinessPriceVersion extends Model
{
    protected static function booted(): void
    {
        static::creating(function (self $price): void {
            $price->uuid ??= (string) Str::uuid();
        });

        static::updating(function (): never {
            throw new LogicException('Business Price versions are immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException('Business Price versions preserve pricing history and cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'valid_from' => 'immutable_datetime',
            'valid_until' => 'immutable_datetime',
        ];
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(BusinessListing::class, 'business_listing_id');
    }

    public function listingVersion(): BelongsTo
    {
        return $this->belongsTo(BusinessListingVersion::class, 'business_listing_version_id');
    }

    public function monetaryUnit(): BelongsTo
    {
        return $this->belongsTo(MonetaryUnit::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'created_by_actor_id');
    }
}
