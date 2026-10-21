<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** A private category proposed for everyone, waiting for (or after) review. */
#[Fillable([
    'uuid',
    'concept_id',
    'parent_concept_id',
    'suggested_by_actor_id',
    'note',
    'status',
    'reviewed_by_actor_id',
    'reviewed_at',
    'review_note',
    'result_concept_id',
])]
class ConceptSuggestion extends Model
{
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const MERGED = 'merged';

    public const REJECTED = 'rejected';

    protected $attributes = ['status' => self::PENDING];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $suggestion) => $suggestion->uuid ??= (string) Str::uuid());
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<Concept, $this> */
    public function concept(): BelongsTo
    {
        return $this->belongsTo(Concept::class);
    }

    /** @return BelongsTo<Concept, $this> */
    public function parentConcept(): BelongsTo
    {
        return $this->belongsTo(Concept::class, 'parent_concept_id');
    }

    /** @return BelongsTo<Concept, $this> */
    public function resultConcept(): BelongsTo
    {
        return $this->belongsTo(Concept::class, 'result_concept_id');
    }

    /** @return BelongsTo<Actor, $this> */
    public function suggestedBy(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'suggested_by_actor_id');
    }
}
