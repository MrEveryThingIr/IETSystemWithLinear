<?php

namespace App\Models;

use App\CommitmentKind;
use App\PlanScheduleFrequency;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'uuid',
    'contract_version_id',
    'employer_actor_id',
    'worker_actor_id',
    'monetary_unit_id',
    'reference_monetary_unit_id',
    'service_title',
    'service_kind',
    'total_quantity',
    'quantity_per_occurrence',
    'unit',
    'unit_rate_minor',
    'reference_unit_rate_minor',
    'reference_usd_amount_minor',
    'reference_market_quote_id',
    'iet_valuation_quote_id',
    'settlement_cycle',
    'payment_due_days',
    'auto_create_plan',
    'auto_recognize_obligation',
    'plan_frequency',
    'plan_starts_on',
    'plan_start_time',
    'plan_duration_minutes',
    'plan_interval',
    'plan_weekdays',
    'plan_selected_dates',
    'plan_ends_on',
    'plan_occurrence_limit',
    'window_before_minutes',
    'window_after_minutes',
    'reminder_offsets',
    'timezone',
])]
class ContractServiceTerm extends Model
{
    public const SETTLEMENT_PER_FULFILLMENT = 'per_fulfillment';

    public const SETTLEMENT_WEEKLY = 'weekly';

    public const SETTLEMENT_MONTHLY = 'monthly';

    public const SETTLEMENT_CONTRACT_END = 'contract_end';

    protected static function booted(): void
    {
        static::creating(function (self $term): void {
            $term->uuid ??= (string) Str::uuid();
        });

        static::updating(function (): never {
            throw new LogicException('Accepted service economics are immutable ContractVersion terms.');
        });

        static::deleting(function (): never {
            throw new LogicException('Contract service terms preserve agreement history and cannot be deleted.');
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<ContractVersion, $this> */
    public function contractVersion(): BelongsTo
    {
        return $this->belongsTo(ContractVersion::class);
    }

    /** @return BelongsTo<Actor, $this> */
    public function employer(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'employer_actor_id');
    }

    /** @return BelongsTo<Actor, $this> */
    public function worker(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'worker_actor_id');
    }

    /** @return BelongsTo<MonetaryUnit, $this> */
    public function monetaryUnit(): BelongsTo
    {
        return $this->belongsTo(MonetaryUnit::class);
    }

    /** @return BelongsTo<MonetaryUnit, $this> */
    public function referenceMonetaryUnit(): BelongsTo
    {
        return $this->belongsTo(MonetaryUnit::class, 'reference_monetary_unit_id');
    }

    /** @return BelongsTo<MarketQuote, $this> */
    public function referenceMarketQuote(): BelongsTo
    {
        return $this->belongsTo(MarketQuote::class, 'reference_market_quote_id');
    }

    /** @return BelongsTo<IetValuationQuote, $this> */
    public function ietValuationQuote(): BelongsTo
    {
        return $this->belongsTo(IetValuationQuote::class, 'iet_valuation_quote_id');
    }

    /** @return HasOne<Commitment, $this> */
    public function commitment(): HasOne
    {
        return $this->hasOne(Commitment::class);
    }

    protected function casts(): array
    {
        return [
            'service_kind' => CommitmentKind::class,
            'total_quantity' => 'decimal:4',
            'quantity_per_occurrence' => 'decimal:4',
            'unit_rate_minor' => 'integer',
            'reference_unit_rate_minor' => 'integer',
            'reference_usd_amount_minor' => 'integer',
            'payment_due_days' => 'integer',
            'auto_create_plan' => 'boolean',
            'auto_recognize_obligation' => 'boolean',
            'plan_frequency' => PlanScheduleFrequency::class,
            'plan_starts_on' => 'immutable_date',
            'plan_duration_minutes' => 'integer',
            'plan_interval' => 'integer',
            'plan_weekdays' => 'array',
            'plan_selected_dates' => 'array',
            'plan_ends_on' => 'immutable_date',
            'plan_occurrence_limit' => 'integer',
            'window_before_minutes' => 'integer',
            'window_after_minutes' => 'integer',
            'reminder_offsets' => 'array',
        ];
    }
}
