<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActorAddress extends Model
{
    protected $fillable = [
        'type',
        'label',
        'country_code',
        'province',
        'city',
        'district',
        'street',
        'alley',
        'building_no',
        'unit',
        'postal_code',
        'latitude',
        'longitude',
        'is_primary',
        'visibility',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function addressable(): MorphTo
    {
        return $this->morphTo();
    }
}
