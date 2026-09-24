<?php

namespace App\Models;

use App\PlanEventType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'uuid',
    'plan_id',
    'actor_id',
    'event_type',
    'payload',
    'created_at',
])]
class PlanEvent extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected static function booted(): void
    {
        static::creating(function (self $event): void {
            $event->uuid ??= (string) Str::uuid();
            $event->created_at ??= now();
        });

        static::updating(function (): never {
            throw new LogicException('Plan events are immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException('Plan events are immutable.');
        });
    }

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
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
            'event_type' => PlanEventType::class,
            'payload' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }
}
