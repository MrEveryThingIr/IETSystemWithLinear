<?php

namespace App\Models;

use App\ConceptAssertionSource;
use Database\Factories\ConceptRelationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'uuid',
    'from_concept_id',
    'relation_type_id',
    'to_concept_id',
    'weight',
    'confidence',
    'source',
    'created_by_actor_id',
    'metadata',
])]
class ConceptRelation extends Model
{
    /** @use HasFactory<ConceptRelationFactory> */
    use HasFactory;

    protected $attributes = [
        'source' => ConceptAssertionSource::Manual->value,
    ];

    protected static function booted(): void
    {
        static::creating(function (self $relation): void {
            $relation->uuid ??= (string) Str::uuid();

            if ((int) $relation->from_concept_id === (int) $relation->to_concept_id) {
                throw new LogicException('A Concept relation must connect distinct Concepts.');
            }
        });

        static::updating(function (self $relation): void {
            if ($relation->isDirty([
                'uuid',
                'from_concept_id',
                'relation_type_id',
                'to_concept_id',
                'created_by_actor_id',
            ])) {
                throw new LogicException('Concept relation identity and provenance cannot be reassigned.');
            }
        });
    }

    /** @return BelongsTo<Concept, $this> */
    public function fromConcept(): BelongsTo
    {
        return $this->belongsTo(Concept::class, 'from_concept_id');
    }

    /** @return BelongsTo<Concept, $this> */
    public function toConcept(): BelongsTo
    {
        return $this->belongsTo(Concept::class, 'to_concept_id');
    }

    /** @return BelongsTo<ConceptRelationType, $this> */
    public function relationType(): BelongsTo
    {
        return $this->belongsTo(ConceptRelationType::class, 'relation_type_id');
    }

    /** @return BelongsTo<Actor, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'created_by_actor_id');
    }

    protected function casts(): array
    {
        return [
            'source' => ConceptAssertionSource::class,
            'weight' => 'decimal:4',
            'confidence' => 'decimal:4',
            'metadata' => 'array',
        ];
    }
}
