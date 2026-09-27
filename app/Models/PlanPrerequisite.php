<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'uuid',
    'plan_id',
    'created_by_actor_id',
    'title',
    'is_required',
    'sort_order',
])]
class PlanPrerequisite extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $prerequisite): void {
            $prerequisite->uuid ??= (string) Str::uuid();
        });

        static::updating(function (): never {
            throw new LogicException('Plan prerequisites preserve planning provenance and cannot be rewritten.');
        });

        static::deleting(function (): never {
            throw new LogicException('Plan prerequisites preserve planning provenance and cannot be deleted.');
        });
    }

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /** @return BelongsTo<Actor, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'created_by_actor_id');
    }

    /** @return HasMany<PlanOccurrencePrerequisiteCheck, $this> */
    public function checks(): HasMany
    {
        return $this->hasMany(PlanOccurrencePrerequisiteCheck::class);
    }

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
