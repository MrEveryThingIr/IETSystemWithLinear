<?php

namespace App\Models;

use App\FulfillmentStatus;
use Database\Factories\FinancialObligationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'uuid',
    'fulfillment_id',
    'contract_version_id',
    'debtor_actor_id',
    'creditor_actor_id',
    'monetary_unit_id',
    'amount_minor',
    'description',
    'due_at',
    'recognized_by_actor_id',
    'recognized_at',
])]
class FinancialObligation extends Model
{
    /** @use HasFactory<FinancialObligationFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $obligation): void {
            $obligation->uuid ??= (string) Str::uuid();
        });

        static::updating(function (): never {
            throw new LogicException('Financial Obligations are immutable economic facts.');
        });

        static::deleting(function (): never {
            throw new LogicException('Financial Obligations preserve economic history and cannot be deleted.');
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<Fulfillment, $this> */
    public function fulfillment(): BelongsTo
    {
        return $this->belongsTo(Fulfillment::class);
    }

    /** @return BelongsTo<ContractVersion, $this> */
    public function contractVersion(): BelongsTo
    {
        return $this->belongsTo(ContractVersion::class);
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
    public function recognizedBy(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'recognized_by_actor_id');
    }

    /** @return HasMany<Settlement, $this> */
    public function settlements(): HasMany
    {
        return $this->hasMany(Settlement::class);
    }

    /** @return HasMany<FinancialObligationEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(FinancialObligationEvent::class)->orderBy('id');
    }

    public function confirmedPaidMinor(): int
    {
        return (int) $this->settlements()
            ->where('status', \App\SettlementStatus::Confirmed->value)
            ->sum('amount_minor');
    }

    public function outstandingMinor(): int
    {
        return max(0, (int) $this->amount_minor - $this->confirmedPaidMinor());
    }

    public function isDisputed(): bool
    {
        $this->loadMissing('fulfillment');

        return $this->fulfillment->status === FulfillmentStatus::Disputed;
    }

    public function isEconomicallyAccepted(): bool
    {
        $this->loadMissing('fulfillment');

        return $this->fulfillment->status === FulfillmentStatus::Accepted;
    }

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'due_at' => 'immutable_datetime',
            'recognized_at' => 'immutable_datetime',
        ];
    }
}
