<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'uuid',
    'base_instrument_id',
    'quote_instrument_id',
    'price',
    'bid',
    'ask',
    'quote_source_id',
    'source_reference',
    'observed_at',
    'effective_at',
    'expires_at',
    'confidence_bps',
    'published_by_user_id',
    'metadata',
])]
class MarketQuote extends Model
{
    protected static function booted(): void
    {
        static::creating(function (self $quote): void {
            $quote->uuid ??= (string) Str::uuid();

            if ((int) $quote->base_instrument_id === (int) $quote->quote_instrument_id) {
                throw new LogicException('A market quote requires two different instruments.');
            }
        });

        static::updating(function (): never {
            throw new LogicException('Published market quotes are immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException('Market quote history cannot be deleted.');
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<EconomicInstrument, $this> */
    public function baseInstrument(): BelongsTo
    {
        return $this->belongsTo(EconomicInstrument::class, 'base_instrument_id');
    }

    /** @return BelongsTo<EconomicInstrument, $this> */
    public function quoteInstrument(): BelongsTo
    {
        return $this->belongsTo(EconomicInstrument::class, 'quote_instrument_id');
    }

    /** @return BelongsTo<QuoteSource, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(QuoteSource::class, 'quote_source_id');
    }

    /** @return BelongsTo<User, $this> */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by_user_id');
    }

    protected function casts(): array
    {
        return [
            'price' => 'decimal:20',
            'bid' => 'decimal:20',
            'ask' => 'decimal:20',
            'observed_at' => 'immutable_datetime',
            'effective_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'confidence_bps' => 'integer',
            'metadata' => 'array',
        ];
    }
}
