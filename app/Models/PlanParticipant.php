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
    'actor_id',
    'assigned_by_actor_id',
    'role',
    'status',
])]
class PlanParticipant extends Model
{
    use HasFactory;

    protected $attributes = [
        'role' => 'participant',
        'status' => 'active',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $participant): void {
            $participant->uuid ??= (string) Str::uuid();
        });

        static::updating(function (): never {
            throw new LogicException('Plan participant assignments are immutable in Phase 12.');
        });

        static::deleting(function (): never {
            throw new LogicException('Plan participant assignments preserve scheduling provenance.');
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

    /** @return BelongsTo<Actor, $this> */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'assigned_by_actor_id');
    }
}
