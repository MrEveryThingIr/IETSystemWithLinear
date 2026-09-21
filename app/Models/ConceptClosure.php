<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ConceptClosure extends Model
{
    public $timestamps = false;

    protected $table = 'concept_closure';

    protected static function booted(): void
    {
        static::creating(function (): never {
            throw new LogicException('Concept closure is derived and cannot be authored directly.');
        });

        static::updating(function (): never {
            throw new LogicException('Concept closure is derived and cannot be authored directly.');
        });

        static::deleting(function (): never {
            throw new LogicException('Concept closure is derived and cannot be authored directly.');
        });
    }

    /** @return BelongsTo<ConceptScheme, $this> */
    public function scheme(): BelongsTo
    {
        return $this->belongsTo(ConceptScheme::class, 'scheme_id');
    }

    /** @return BelongsTo<Concept, $this> */
    public function ancestor(): BelongsTo
    {
        return $this->belongsTo(Concept::class, 'ancestor_concept_id');
    }

    /** @return BelongsTo<Concept, $this> */
    public function descendant(): BelongsTo
    {
        return $this->belongsTo(Concept::class, 'descendant_concept_id');
    }

    protected function casts(): array
    {
        return [
            'min_depth' => 'integer',
        ];
    }
}
