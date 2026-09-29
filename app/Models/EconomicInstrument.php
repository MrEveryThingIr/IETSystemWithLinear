<?php

namespace App\Models;

use App\EconomicInstrumentKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'uuid',
    'code',
    'name',
    'kind',
    'monetary_unit_id',
    'settlement_enabled',
    'active',
    'metadata',
])]
class EconomicInstrument extends Model
{
    protected $attributes = [
        'settlement_enabled' => false,
        'active' => true,
    ];

    protected static function booted(): void
    {
        static::creating(function (self $instrument): void {
            $instrument->uuid ??= (string) Str::uuid();
            $instrument->code = strtoupper(trim((string) $instrument->code));
        });

        static::updating(function (self $instrument): void {
            if ($instrument->isDirty(['uuid', 'code', 'kind', 'monetary_unit_id'])) {
                throw new LogicException('Economic instrument identity, kind and monetary-unit binding are immutable.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Economic instruments preserve valuation history and cannot be deleted.');
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<MonetaryUnit, $this> */
    public function monetaryUnit(): BelongsTo
    {
        return $this->belongsTo(MonetaryUnit::class);
    }

    /** @return HasMany<MarketQuote, $this> */
    public function baseQuotes(): HasMany
    {
        return $this->hasMany(MarketQuote::class, 'base_instrument_id');
    }

    /** @return HasMany<MarketQuote, $this> */
    public function counterQuotes(): HasMany
    {
        return $this->hasMany(MarketQuote::class, 'quote_instrument_id');
    }

    protected function casts(): array
    {
        return [
            'kind' => EconomicInstrumentKind::class,
            'settlement_enabled' => 'boolean',
            'active' => 'boolean',
            'metadata' => 'array',
        ];
    }
}
