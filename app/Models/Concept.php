<?php

namespace App\Models;

use App\ConceptStatus;
use Database\Factories\ConceptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'uuid',
    'vocabulary_id',
    'slug',
    'status',
    'merged_into_concept_id',
    'summary',
    'metadata',
])]
class Concept extends Model
{
    /** @use HasFactory<ConceptFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => ConceptStatus::Active->value,
    ];

    private bool $applyingLifecycle = false;

    protected static function booted(): void
    {
        static::creating(function (self $concept): void {
            $concept->uuid ??= (string) Str::uuid();

            if ($concept->status === ConceptStatus::Merged || $concept->merged_into_concept_id !== null) {
                throw new LogicException('New Concepts cannot begin in a merged state.');
            }
        });

        static::updating(function (self $concept): void {
            if ($concept->isDirty(['uuid', 'vocabulary_id'])) {
                throw new LogicException('Concept identity cannot be reassigned.');
            }

            if ($concept->isDirty(['status', 'merged_into_concept_id']) && ! $concept->applyingLifecycle) {
                throw new LogicException('Concept lifecycle changes require a dedicated Action.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Concept identity is preserved; merge or deprecate it instead.');
        });
    }

    /** @param array{status: string, merged_into_concept_id: int|null} $attributes */
    public function applyLifecycle(array $attributes): void
    {
        $this->applyingLifecycle = true;

        try {
            $this->update($attributes);
        } finally {
            $this->applyingLifecycle = false;
        }
    }

    public function canonical(): self
    {
        $current = self::query()->findOrFail($this->getKey());
        $visited = [];

        while ($current->status === ConceptStatus::Merged) {
            if ($current->merged_into_concept_id === null || isset($visited[$current->id])) {
                throw new LogicException('Concept merge chain is invalid.');
            }

            $visited[$current->id] = true;
            $current = self::query()->findOrFail($current->merged_into_concept_id);
        }

        return $current;
    }

    /** @return BelongsTo<ConceptVocabulary, $this> */
    public function vocabulary(): BelongsTo
    {
        return $this->belongsTo(ConceptVocabulary::class, 'vocabulary_id');
    }

    /** @return BelongsTo<Concept, $this> */
    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_into_concept_id');
    }

    /** @return HasMany<ConceptLabel, $this> */
    public function labels(): HasMany
    {
        return $this->hasMany(ConceptLabel::class);
    }

    /** @return HasMany<ConceptSchemeMembership, $this> */
    public function schemeMemberships(): HasMany
    {
        return $this->hasMany(ConceptSchemeMembership::class);
    }

    protected function casts(): array
    {
        return [
            'status' => ConceptStatus::class,
            'metadata' => 'array',
        ];
    }
}
