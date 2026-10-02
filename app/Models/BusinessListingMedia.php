<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'uuid',
    'business_listing_version_id',
    'asset_id',
    'role',
    'position',
    'caption',
    'visibility',
    'created_by_actor_id',
])]
class BusinessListingMedia extends Model
{
    protected $table = 'business_listing_media';

    protected static function booted(): void
    {
        static::creating(function (self $media): void {
            $media->uuid ??= (string) Str::uuid();
            $media->assertEditableVersion();
        });

        static::updating(function (self $media): void {
            if ($media->isDirty([
                'uuid',
                'business_listing_version_id',
                'asset_id',
                'created_by_actor_id',
            ])) {
                throw new LogicException('Business Listing media provenance is immutable.');
            }

            $media->assertEditableVersion();
        });

        static::deleting(function (self $media): void {
            $media->assertEditableVersion();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<BusinessListingVersion, $this> */
    public function listingVersion(): BelongsTo
    {
        return $this->belongsTo(BusinessListingVersion::class, 'business_listing_version_id');
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /** @return BelongsTo<Actor, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'created_by_actor_id');
    }

    private function assertEditableVersion(): void
    {
        $this->loadMissing('listingVersion');

        if ($this->listingVersion->published_at !== null) {
            throw new LogicException('Published Business Listing media is immutable.');
        }
    }
}
