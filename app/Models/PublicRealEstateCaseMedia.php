<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublicRealEstateCaseMedia extends Model
{
    protected $table = 'public_real_estate_case_media';

    protected $fillable = [
        'public_real_estate_case_id',
        'kind',
        'origin',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function realEstateCase(): BelongsTo
    {
        return $this->belongsTo(PublicRealEstateCase::class, 'public_real_estate_case_id');
    }
}
