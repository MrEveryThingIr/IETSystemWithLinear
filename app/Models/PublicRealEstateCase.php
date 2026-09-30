<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PublicRealEstateCase extends Model
{
    protected $fillable = [
        'public_intake_portal_id',
        'business_contact_id',
        'reference_code',
        'intent',
        'transaction_mode',
        'contact_name',
        'phone',
        'exact_address',
        'public_area',
        'property_class',
        'property_subtype',
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
        'asking_price',
        'deposit_amount',
        'monthly_rent_amount',
        'price_unit',
        'notes',
        'status',
        'ip_hash',
        'user_agent',
        'preview_token_hash',
        'preview_expires_at',
        'preview_viewed_at',
    ];

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
            'asking_price' => 'decimal:0',
            'deposit_amount' => 'decimal:0',
            'monthly_rent_amount' => 'decimal:0',
            'preview_expires_at' => 'datetime',
            'preview_viewed_at' => 'datetime',
        ];
    }

    public function portal(): BelongsTo
    {
        return $this->belongsTo(PublicIntakePortal::class, 'public_intake_portal_id');
    }

    public function getRouteKeyName(): string
    {
        return 'reference_code';
    }

    public function media(): HasMany
    {
        return $this->hasMany(PublicRealEstateCaseMedia::class, 'public_real_estate_case_id')
            ->orderBy('kind')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

}
