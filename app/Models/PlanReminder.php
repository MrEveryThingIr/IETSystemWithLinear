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
    'plan_id',
    'schedule_rule_id',
    'created_by_actor_id',
    'minutes_before',
    'channel',
    'status',
])]
class PlanReminder extends Model
{
    use HasFactory;

    protected $attributes = [
        'channel' => 'app',
        'status' => 'active',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $reminder): void {
            $reminder->uuid ??= (string) Str::uuid();
        });

        static::updating(function (): never {
            throw new LogicException('Planner reminder configuration is immutable in Phase 12.');
        });

        static::deleting(function (): never {
            throw new LogicException('Planner reminder configuration preserves scheduling provenance.');
        });
    }

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /** @return BelongsTo<PlanScheduleRule, $this> */
    public function scheduleRule(): BelongsTo
    {
        return $this->belongsTo(PlanScheduleRule::class, 'schedule_rule_id');
    }

    /** @return BelongsTo<Actor, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'created_by_actor_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'minutes_before' => 'integer',
        ];
    }
}
