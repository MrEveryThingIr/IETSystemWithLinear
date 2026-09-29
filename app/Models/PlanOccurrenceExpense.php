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
    'plan_expense_estimate_id',
    'monetary_unit_id',
    'created_by_actor_id',
    'label',
    'amount_minor',
    'note',
    'occurred_at',
])]
class PlanOccurrenceExpense extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $expense): void {
            $expense->uuid ??= (string) Str::uuid();
        });

        static::updating(function (): never {
            throw new LogicException('Recorded Planner expenses are immutable observations.');
        });

        static::deleting(function (): never {
            throw new LogicException('Recorded Planner expenses preserve execution history and cannot be deleted.');
        });
    }

    /** @return BelongsTo<PlanOccurrence, $this> */
    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(PlanOccurrence::class, 'plan_occurrence_id');
    }

    /** @return BelongsTo<PlanExpenseEstimate, $this> */
    public function estimate(): BelongsTo
    {
        return $this->belongsTo(PlanExpenseEstimate::class, 'plan_expense_estimate_id');
    }

    /** @return BelongsTo<MonetaryUnit, $this> */
    public function monetaryUnit(): BelongsTo
    {
        return $this->belongsTo(MonetaryUnit::class);
    }

    /** @return BelongsTo<Actor, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'created_by_actor_id');
    }

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'occurred_at' => 'immutable_datetime',
        ];
    }
}
