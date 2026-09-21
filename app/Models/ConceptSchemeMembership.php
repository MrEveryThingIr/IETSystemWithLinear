<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'scheme_id',
    'concept_id',
    'metadata',
])]
class ConceptSchemeMembership extends Model
{
    /** @return BelongsTo<ConceptScheme, $this> */
    public function scheme(): BelongsTo
    {
        return $this->belongsTo(ConceptScheme::class, 'scheme_id');
    }

    /** @return BelongsTo<Concept, $this> */
    public function concept(): BelongsTo
    {
        return $this->belongsTo(Concept::class);
    }

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }
}
