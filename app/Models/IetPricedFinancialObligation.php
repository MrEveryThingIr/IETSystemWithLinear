<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'financial_obligation_id',
    'reference_usd_amount_minor',
    'valuation_quote_id',
    'iet_amount',
])]
class IetPricedFinancialObligation extends Model
{
    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('IET obligation pricing snapshots are immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException('IET obligation pricing snapshots preserve financial history.');
        });
    }

    /** @return BelongsTo<FinancialObligation, $this> */
    public function obligation(): BelongsTo
    {
        return $this->belongsTo(FinancialObligation::class, 'financial_obligation_id');
    }

    /** @return BelongsTo<IetValuationQuote, $this> */
    public function valuationQuote(): BelongsTo
    {
        return $this->belongsTo(IetValuationQuote::class, 'valuation_quote_id');
    }

    protected function casts(): array
    {
        return [
            'reference_usd_amount_minor' => 'integer',
            'iet_amount' => 'integer',
        ];
    }
}
