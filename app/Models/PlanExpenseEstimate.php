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
    'monetary_unit_id',
    'label',
    'amount_minor',
    'sort_order',
])]
class PlanExpenseEstimate extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $estimate): void {
            $estimate->uuid ??= (string) Str::uuid();
        });

        static::updating(function (): never {
            throw new LogicException('Plan expense estimates preserve planning provenance and cannot be rewritten.');
        });

        static::deleting(function (): never {
            throw new LogicException('Plan expense estimates preserve planning provenance and cannot be deleted.');
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

    /** @return BelongsTo<MonetaryUnit, $this> */
    public function monetaryUnit(): BelongsTo
    {
        return $this->belongsTo(MonetaryUnit::class);
    }

    /** @return HasMany<PlanOccurrenceExpense, $this> */
    public function actualExpenses(): HasMany
    {
        return $this->hasMany(PlanOccurrenceExpense::class);
    }

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'sort_order' => 'integer',
        ];
    }
}
