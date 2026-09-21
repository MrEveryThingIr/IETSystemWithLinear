<?php

namespace App\Models;

use App\ConceptCatalogStatus;
use App\ConceptVocabularyScope;
use Database\Factories\ConceptVocabularyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'uuid',
    'scope_type',
    'scope_id',
    'name',
    'slug',
    'status',
    'created_by_actor_id',
    'metadata',
])]
class ConceptVocabulary extends Model
{
    /** @use HasFactory<ConceptVocabularyFactory> */
    use HasFactory;

    protected $attributes = [
        'scope_id' => 0,
        'status' => ConceptCatalogStatus::Active->value,
    ];

    protected static function booted(): void
    {
        static::creating(function (self $vocabulary): void {
            $vocabulary->uuid ??= (string) Str::uuid();

            match ($vocabulary->scope_type) {
                ConceptVocabularyScope::Platform => throw_unless(
                    (int) $vocabulary->scope_id === 0,
                    LogicException::class,
                    'Platform Concept Vocabularies must use scope_id 0.',
                ),
                ConceptVocabularyScope::Actor => throw_unless(
                    Actor::query()->whereKey($vocabulary->scope_id)->exists(),
                    LogicException::class,
                    'Actor-scoped Concept Vocabulary owner does not exist.',
                ),
                ConceptVocabularyScope::Group => throw_unless(
                    Group::query()->whereKey($vocabulary->scope_id)->exists(),
                    LogicException::class,
                    'Group-scoped Concept Vocabulary owner does not exist.',
                ),
            };
        });

        static::updating(function (self $vocabulary): void {
            if ($vocabulary->isDirty(['uuid', 'scope_type', 'scope_id', 'created_by_actor_id'])) {
                throw new LogicException('Concept Vocabulary identity and provenance cannot be reassigned.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Concept Vocabularies are preserved; deprecate them instead.');
        });
    }

    /** @return BelongsTo<Actor, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'created_by_actor_id');
    }

    /** @return HasMany<Concept, $this> */
    public function concepts(): HasMany
    {
        return $this->hasMany(Concept::class, 'vocabulary_id');
    }

    /** @return HasMany<ConceptScheme, $this> */
    public function schemes(): HasMany
    {
        return $this->hasMany(ConceptScheme::class, 'vocabulary_id');
    }

    protected function casts(): array
    {
        return [
            'scope_type' => ConceptVocabularyScope::class,
            'status' => ConceptCatalogStatus::class,
            'metadata' => 'array',
        ];
    }
}
