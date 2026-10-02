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
    'business_id',
    'business_category_id',
    'listing_type',
    'status',
    'visibility',
    'current_version_id',
    'published_version_id',
])]
class BusinessListing extends Model
{
    protected static function booted(): void
    {
        static::creating(function (self $listing): void {
            $listing->uuid ??= (string) Str::uuid();
        });

        static::updating(function (self $listing): void {
            if ($listing->isDirty(['uuid', 'business_id', 'listing_type'])) {
                throw new LogicException('Business Listing identity, Business and type are immutable.');
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<Business, $this> */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /** @return BelongsTo<BusinessCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(BusinessCategory::class, 'business_category_id');
    }

    /** @return HasMany<BusinessListingVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(BusinessListingVersion::class)->orderByDesc('version_number');
    }

    /** @return BelongsTo<BusinessListingVersion, $this> */
    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(BusinessListingVersion::class, 'current_version_id');
    }

    /** @return BelongsTo<BusinessListingVersion, $this> */
    public function publishedVersion(): BelongsTo
    {
        return $this->belongsTo(BusinessListingVersion::class, 'published_version_id');
    }

    /** @return HasMany<BusinessPriceVersion, $this> */
    public function prices(): HasMany
    {
        return $this->hasMany(BusinessPriceVersion::class)->orderByDesc('valid_from')->orderByDesc('id');
    }
}
