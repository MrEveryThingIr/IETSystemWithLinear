<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'uuid',
    'plan_occurrence_id',
    'plan_prerequisite_id',
    'completed_by_actor_id',
    'completed_at',
])]
class PlanOccurrencePrerequisiteCheck extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $check): void {
            $check->uuid ??= (string) Str::uuid();
        });

        static::updating(function (self $check): void {
            if ($check->isDirty(['uuid', 'plan_occurrence_id', 'plan_prerequisite_id'])) {
                throw new LogicException('Occurrence prerequisite identity is immutable.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Occurrence prerequisite checks are cleared in place instead of deleted.');
        });
    }

    /** @return BelongsTo<PlanOccurrence, $this> */
    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(PlanOccurrence::class, 'plan_occurrence_id');
    }

    /** @return BelongsTo<PlanPrerequisite, $this> */
    public function prerequisite(): BelongsTo
    {
        return $this->belongsTo(PlanPrerequisite::class, 'plan_prerequisite_id');
    }

    /** @return BelongsTo<Actor, $this> */
    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'completed_by_actor_id');
    }

    protected function casts(): array
    {
        return ['completed_at' => 'immutable_datetime'];
    }
}
