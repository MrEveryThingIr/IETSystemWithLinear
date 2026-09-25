<?php

namespace App\Models;

use Database\Factories\CommitmentPlanBindingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'uuid',
    'commitment_id',
    'plan_id',
    'bound_by_actor_id',
])]
class CommitmentPlanBinding extends Model
{
    /** @use HasFactory<CommitmentPlanBindingFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $binding): void {
            $binding->uuid ??= (string) Str::uuid();
        });

        static::updating(function (): never {
            throw new LogicException('Commitment Planner bindings are immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException('Commitment Planner bindings preserve scheduling provenance.');
        });
    }

    /** @return BelongsTo<Commitment, $this> */
    public function commitment(): BelongsTo
    {
        return $this->belongsTo(Commitment::class);
    }

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /** @return BelongsTo<Actor, $this> */
    public function boundBy(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'bound_by_actor_id');
    }
}
