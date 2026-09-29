<?php

namespace App\Models;

use App\SettlementStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'uuid',
    'contract_id',
    'debtor_actor_id',
    'creditor_actor_id',
    'monetary_unit_id',
    'proposed_by_actor_id',
    'amount_minor',
    'method',
    'paid_at',
    'reference',
    'note',
])]
class ContractSettlementBatch extends Model
{
    protected static function booted(): void
    {
        static::creating(function (self $batch): void {
            $batch->uuid ??= (string) Str::uuid();
        });

        static::updating(function (): never {
            throw new LogicException('Settlement batch payment facts are immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException('Settlement batches preserve payment history and cannot be deleted.');
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<Contract, $this> */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /** @return BelongsTo<Actor, $this> */
    public function debtor(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'debtor_actor_id');
    }

    /** @return BelongsTo<Actor, $this> */
    public function creditor(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'creditor_actor_id');
    }

    /** @return BelongsTo<MonetaryUnit, $this> */
    public function monetaryUnit(): BelongsTo
    {
        return $this->belongsTo(MonetaryUnit::class);
    }

    /** @return BelongsTo<Actor, $this> */
    public function proposedBy(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'proposed_by_actor_id');
    }

    /** @return HasMany<Settlement, $this> */
    public function settlements(): HasMany
    {
        return $this->hasMany(Settlement::class);
    }

    public function confirmedMinor(): int
    {
        return (int) $this->settlements()
            ->where('status', SettlementStatus::Confirmed->value)
            ->sum('amount_minor');
    }

    public function pendingMinor(): int
    {
        return (int) $this->settlements()
            ->where('status', SettlementStatus::PendingConfirmation->value)
            ->sum('amount_minor');
    }

    public function derivedStatus(): string
    {
        $this->loadMissing('settlements');

        if ($this->settlements->isEmpty()) {
            return 'empty';
        }

        if ($this->settlements->every(fn (Settlement $settlement): bool => $settlement->status === SettlementStatus::Confirmed)) {
            return 'confirmed';
        }

        if ($this->settlements->every(fn (Settlement $settlement): bool => $settlement->status === SettlementStatus::Rejected)) {
            return 'rejected';
        }

        if ($this->settlements->contains(fn (Settlement $settlement): bool => $settlement->status === SettlementStatus::PendingConfirmation)) {
            return 'pending_confirmation';
        }

        return 'partial';
    }

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'paid_at' => 'immutable_datetime',
        ];
    }
}
