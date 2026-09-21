<?php

namespace App\Models;

use App\ConceptAssertionPredicate;
use App\ConceptAssertionSource;
use App\ConceptAssertionSubject;
use App\ConceptAssertionVisibility;
use Database\Factories\ConceptAssertionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'uuid',
    'subject_type',
    'subject_id',
    'concept_id',
    'predicate',
    'scheme_id',
    'weight',
    'confidence',
    'source',
    'visibility',
    'valid_from',
    'valid_until',
    'created_by_actor_id',
    'metadata',
])]
class ConceptAssertion extends Model
{
    /** @use HasFactory<ConceptAssertionFactory> */
    use HasFactory;

    protected $attributes = [
        'source' => ConceptAssertionSource::Manual->value,
        'visibility' => ConceptAssertionVisibility::Inherited->value,
    ];

    protected static function booted(): void
    {
        static::creating(function (self $assertion): void {
            $assertion->uuid ??= (string) Str::uuid();
        });

        static::updating(function (self $assertion): void {
            if ($assertion->isDirty([
                'uuid',
                'subject_type',
                'subject_id',
                'concept_id',
                'predicate',
                'scheme_id',
                'created_by_actor_id',
            ])) {
                throw new LogicException('Concept assertion semantic identity and provenance cannot be reassigned.');
            }

            $assertion->assertRevisionIsMutable();
        });

        static::deleting(function (self $assertion): void {
            $assertion->assertRevisionIsMutable();
        });
    }

    /** @return BelongsTo<Concept, $this> */
    public function concept(): BelongsTo
    {
        return $this->belongsTo(Concept::class);
    }

    /** @return BelongsTo<ConceptScheme, $this> */
    public function scheme(): BelongsTo
    {
        return $this->belongsTo(ConceptScheme::class, 'scheme_id');
    }

    /** @return BelongsTo<Actor, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'created_by_actor_id');
    }

    public function subjectModel(): Model
    {
        $subjectClass = $this->subject_type->modelClass();

        return $subjectClass::query()->findOrFail($this->subject_id);
    }

    /** @param Builder<ConceptAssertion> $query */
    public function scopeForSubject(Builder $query, Model $subject): Builder
    {
        $type = ConceptAssertionSubject::fromModel($subject);

        return $query
            ->where('subject_type', $type->value)
            ->where('subject_id', $subject->getKey());
    }

    protected function casts(): array
    {
        return [
            'subject_type' => ConceptAssertionSubject::class,
            'predicate' => ConceptAssertionPredicate::class,
            'source' => ConceptAssertionSource::class,
            'visibility' => ConceptAssertionVisibility::class,
            'weight' => 'decimal:4',
            'confidence' => 'decimal:4',
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
            'metadata' => 'array',
        ];
    }

    private function assertRevisionIsMutable(): void
    {
        if ($this->subject_type !== ConceptAssertionSubject::SpaceContentRevision) {
            return;
        }

        $revision = SpaceContentRevision::query()->find($this->subject_id);

        if ($revision instanceof SpaceContentRevision && $revision->hasVerifiableManifest()) {
            throw new LogicException('Published revision-bound Concept assertions are immutable evidence.');
        }
    }
}
