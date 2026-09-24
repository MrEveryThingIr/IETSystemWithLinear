<?php

namespace App\Models;

use App\RelationshipStatus;
use Database\Factories\RelationshipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'title',
    'purpose_concept_id',
    'originating_intent_id',
    'created_by_actor_id',
    'metadata',
])]
class Relationship extends Model
{
    /** @use HasFactory<RelationshipFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => RelationshipStatus::Proposed->value,
    ];

    private bool $applyingLifecycle = false;

    protected static function booted(): void
    {
        static::creating(function (self $relationship): void {
            $relationship->uuid ??= (string) Str::uuid();
        });

        static::updating(function (self $relationship): void {
            if ($relationship->isDirty([
                'uuid',
                'purpose_concept_id',
                'originating_intent_id',
                'created_by_actor_id',
            ])) {
                throw new LogicException('Relationship identity, purpose, origin, and creator cannot be reassigned.');
            }

            if ($relationship->isDirty(['status', 'activated_at', 'ended_at', 'cancelled_at'])
                && ! $relationship->applyingLifecycle) {
                throw new LogicException('Relationship lifecycle changes require a dedicated Action.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Relationships preserve coordination history and cannot be deleted directly.');
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<Concept, $this> */
    public function purposeConcept(): BelongsTo
    {
        return $this->belongsTo(Concept::class, 'purpose_concept_id');
    }

    /** @return BelongsTo<ActorProfileIntent, $this> */
    public function originatingIntent(): BelongsTo
    {
        return $this->belongsTo(ActorProfileIntent::class, 'originating_intent_id');
    }

    /** @return BelongsTo<Actor, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'created_by_actor_id');
    }

    /** @return HasMany<RelationshipParticipant, $this> */
    public function participants(): HasMany
    {
        return $this->hasMany(RelationshipParticipant::class);
    }

    /** @return HasMany<RelationshipEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(RelationshipEvent::class)->orderBy('id');
    }

    /** @return HasOne<RelationshipContext, $this> */
    public function contextBinding(): HasOne
    {
        return $this->hasOne(RelationshipContext::class);
    }

    public function applyStatus(RelationshipStatus $status): void
    {
        $current = $this->status;

        $allowed = match ($current) {
            RelationshipStatus::Proposed => [RelationshipStatus::Active, RelationshipStatus::Cancelled],
            RelationshipStatus::Active => [RelationshipStatus::Ended],
            RelationshipStatus::Ended, RelationshipStatus::Cancelled => [],
        };

        if (! in_array($status, $allowed, true)) {
            throw new LogicException("Relationship cannot transition from {$current->value} to {$status->value}.");
        }

        $attributes = ['status' => $status];

        if ($status === RelationshipStatus::Active) {
            $attributes['activated_at'] = now();
        } elseif ($status === RelationshipStatus::Ended) {
            $attributes['ended_at'] = now();
        } elseif ($status === RelationshipStatus::Cancelled) {
            $attributes['cancelled_at'] = now();
        }

        $this->applyingLifecycle = true;

        try {
            $this->forceFill($attributes)->save();
        } finally {
            $this->applyingLifecycle = false;
        }
    }

    protected function casts(): array
    {
        return [
            'status' => RelationshipStatus::class,
            'activated_at' => 'datetime',
            'ended_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
