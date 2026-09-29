<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'uuid',
    'user_id',
    'source_type',
    'source_uuid',
    'usd_amount_minor',
    'valuation_quote_id',
    'iet_amount',
    'journal_entry_id',
])]
class IetInternalCharge extends Model
{
    protected static function booted(): void
    {
        static::creating(function (self $charge): void {
            $charge->uuid ??= (string) Str::uuid();
        });

        static::updating(function (): never {
            throw new LogicException('IET internal charges are immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException('IET internal charges preserve settlement history.');
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<IetValuationQuote, $this> */
    public function valuationQuote(): BelongsTo
    {
        return $this->belongsTo(IetValuationQuote::class, 'valuation_quote_id');
    }

    /** @return BelongsTo<JournalEntry, $this> */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    protected function casts(): array
    {
        return [
            'usd_amount_minor' => 'integer',
            'iet_amount' => 'integer',
        ];
    }
}
