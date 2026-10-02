<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'business_listing_version_id',
    'transaction_mode',
    'property_class',
    'property_subtype',
    'exact_address',
    'public_area',
    'land_area',
    'construction_area',
    'width',
    'length',
    'frontage_count',
    'built_year',
    'built_year_calendar',
    'building_age_years',
    'building_condition',
    'bedrooms',
    'bedrooms_note',
    'cabinet_type',
    'has_false_ceiling',
    'false_ceiling_note',
    'heating_system',
    'cooling_system',
    'yard_finish',
    'flooring_type',
    'flooring_note',
    'has_parking',
    'parking_type',
    'parking_spaces',
    'car_capacity',
    'motorbike_capacity',
    'parking_note',
    'roof_finish',
    'roof_note',
    'roof_has_parapet',
    'has_western_toilet',
    'has_iranian_toilet',
    'facilities',
])]
class BusinessPropertyDetails extends Model
{
    protected static function booted(): void
    {
        static::updating(function (self $details): void {
            $details->loadMissing('listingVersion');

            if ($details->listingVersion->published_at !== null) {
                throw new LogicException('Published property details are immutable.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'land_area' => 'decimal:2',
            'construction_area' => 'decimal:2',
            'width' => 'decimal:2',
            'length' => 'decimal:2',
            'has_false_ceiling' => 'boolean',
            'has_parking' => 'boolean',
            'roof_has_parapet' => 'boolean',
            'has_western_toilet' => 'boolean',
            'has_iranian_toilet' => 'boolean',
            'facilities' => 'array',
        ];
    }

    /** @return BelongsTo<BusinessListingVersion, $this> */
    public function listingVersion(): BelongsTo
    {
        return $this->belongsTo(BusinessListingVersion::class, 'business_listing_version_id');
    }
}
