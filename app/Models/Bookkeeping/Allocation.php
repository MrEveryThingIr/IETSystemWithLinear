<?php

namespace App\Models\Bookkeeping;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** How much of a payment (refund, write-off, in-kind settlement) applies to one debt. */
#[Fillable(['transaction_id', 'obligation_id', 'amount'])]
class Allocation extends Model
{
    protected $table = 'bk_allocations';

    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Allocations never change.');
        });

        static::deleting(function (): never {
            throw new LogicException('Allocations are never deleted.');
        });
    }

    /** @return BelongsTo<Transaction, $this> */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /** @return BelongsTo<Transaction, $this> */
    public function obligation(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'obligation_id');
    }
}
