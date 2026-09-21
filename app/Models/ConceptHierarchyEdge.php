<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'uuid',
    'scheme_id',
    'parent_concept_id',
    'child_concept_id',
    'sort_order',
    'created_by_actor_id',
    'metadata',
])]
class ConceptHierarchyEdge extends Model
{
    protected $attributes = [
        'sort_order' => 0,
    ];

    protected static function booted(): void
    {
        static::creating(function (self $edge): void {
            $edge->uuid ??= (string) Str::uuid();

            if ((int) $edge->parent_concept_id === (int) $edge->child_concept_id) {
                throw new LogicException('A Concept cannot be its own hierarchy parent.');
            }
        });

        static::updating(function (self $edge): void {
            if ($edge->isDirty([
                'uuid',
                'scheme_id',
                'parent_concept_id',
                'child_concept_id',
                'created_by_actor_id',
            ])) {
                throw new LogicException('Concept hierarchy edge identity cannot be reassigned.');
            }
        });
    }

    /** @return BelongsTo<ConceptScheme, $this> */
    public function scheme(): BelongsTo
    {
        return $this->belongsTo(ConceptScheme::class, 'scheme_id');
    }

    /** @return BelongsTo<Concept, $this> */
    public function parentConcept(): BelongsTo
    {
        return $this->belongsTo(Concept::class, 'parent_concept_id');
    }

    /** @return BelongsTo<Concept, $this> */
    public function childConcept(): BelongsTo
    {
        return $this->belongsTo(Concept::class, 'child_concept_id');
    }

    /** @return BelongsTo<Actor, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'created_by_actor_id');
    }

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'metadata' => 'array',
        ];
    }
}
