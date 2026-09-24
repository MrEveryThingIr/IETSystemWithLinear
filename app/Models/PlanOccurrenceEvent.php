<?php

namespace App\Models;

use App\PlanOccurrenceEventType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'uuid',
    'plan_occurrence_id',
    'actor_id',
    'event_type',
    'payload',
    'created_at',
])]
class PlanOccurrenceEvent extends Model
{
    public $timestamps = false;

    protected static function booted(): void
    {
        static::creating(function (self $event): void {
            $event->uuid ??= (string) Str::uuid();
            $event->created_at ??= now();
        });

        static::updating(function (): never {
            throw new LogicException('Occurrence events are immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException('Occurrence events are immutable.');
        });
    }

    /** @return BelongsTo<PlanOccurrence, $this> */
    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(PlanOccurrence::class, 'plan_occurrence_id');
    }

    /** @return BelongsTo<Actor, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(Actor::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'event_type' => PlanOccurrenceEventType::class,
            'payload' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }
}
