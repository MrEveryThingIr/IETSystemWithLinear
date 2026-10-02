<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'uuid',
    'business_listing_id',
    'version_number',
    'title',
    'slug',
    'short_description',
    'description',
    'structured_data',
    'created_by_actor_id',
    'presentation_content_id',
    'published_at',
])]
class BusinessListingVersion extends Model
{
    protected static function booted(): void
    {
        static::creating(function (self $version): void {
            $version->uuid ??= (string) Str::uuid();
            $version->slug = Str::slug($version->slug ?: $version->title);
        });

        static::updating(function (self $version): void {
            if ($version->getOriginal('published_at') !== null) {
                throw new LogicException('Published Business Listing versions are immutable.');
            }

            if ($version->isDirty([
                'uuid',
                'business_listing_id',
                'version_number',
                'created_by_actor_id',
            ])) {
                throw new LogicException('Business Listing version provenance is immutable.');
            }
        });

        static::deleting(function (self $version): void {
            if ($version->published_at !== null) {
                throw new LogicException('Published Business Listing versions cannot be deleted.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'structured_data' => 'array',
            'published_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<BusinessListing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(BusinessListing::class, 'business_listing_id');
    }

    /** @return BelongsTo<Actor, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'created_by_actor_id');
    }

    /** @return HasOne<BusinessPropertyDetails, $this> */
    public function propertyDetails(): HasOne
    {
        return $this->hasOne(BusinessPropertyDetails::class);
    }

    /** @return HasMany<BusinessListingMedia, $this> */
    public function media(): HasMany
    {
        return $this->hasMany(BusinessListingMedia::class)->orderBy('position')->orderBy('id');
    }

    /** @return BelongsTo<SpaceContent, $this> */
    public function presentationContent(): BelongsTo
    {
        return $this->belongsTo(SpaceContent::class, 'presentation_content_id');
    }
}
