<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ContactPoint extends Model
{
    protected $fillable = [
        'kind',
        'label',
        'value',
        'normalized_value',
        'is_primary',
        'is_verified',
        'verified_at',
        'visibility',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'is_verified' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    public function contactable(): MorphTo
    {
        return $this->morphTo();
    }
}
