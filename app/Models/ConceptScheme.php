<?php

namespace App\Models;

use App\ConceptCatalogStatus;
use Database\Factories\ConceptSchemeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'uuid',
    'vocabulary_id',
    'name',
    'slug',
    'description',
    'status',
    'created_by_actor_id',
    'metadata',
])]
class ConceptScheme extends Model
{
    /** @use HasFactory<ConceptSchemeFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => ConceptCatalogStatus::Active->value,
    ];

    protected static function booted(): void
    {
        static::creating(function (self $scheme): void {
            $scheme->uuid ??= (string) Str::uuid();
        });

        static::updating(function (self $scheme): void {
            if ($scheme->isDirty(['uuid', 'vocabulary_id', 'created_by_actor_id'])) {
                throw new LogicException('Concept Scheme identity and provenance cannot be reassigned.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Concept Schemes are preserved; deprecate them instead.');
        });
    }

    /** @return BelongsTo<ConceptVocabulary, $this> */
    public function vocabulary(): BelongsTo
    {
        return $this->belongsTo(ConceptVocabulary::class, 'vocabulary_id');
    }

    /** @return BelongsTo<Actor, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'created_by_actor_id');
    }

    /** @return BelongsToMany<Concept, $this> */
    public function concepts(): BelongsToMany
    {
        return $this->belongsToMany(
            Concept::class,
            'concept_scheme_memberships',
            'scheme_id',
            'concept_id',
        )->withPivot(['metadata'])->withTimestamps();
    }

    /** @return HasMany<ConceptSchemeMembership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(ConceptSchemeMembership::class, 'scheme_id');
    }

    /** @return HasMany<ConceptHierarchyEdge, $this> */
    public function hierarchyEdges(): HasMany
    {
        return $this->hasMany(ConceptHierarchyEdge::class, 'scheme_id');
    }

    /** @return HasMany<ConceptClosure, $this> */
    public function closure(): HasMany
    {
        return $this->hasMany(ConceptClosure::class, 'scheme_id');
    }

    protected function casts(): array
    {
        return [
            'status' => ConceptCatalogStatus::class,
            'metadata' => 'array',
        ];
    }
}
