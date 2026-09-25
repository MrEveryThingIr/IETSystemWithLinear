<?php

namespace App\Models;

use App\FinancialObligationEventType;
use Database\Factories\FinancialObligationEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'uuid',
    'financial_obligation_id',
    'settlement_id',
    'journal_entry_id',
    'actor_id',
    'event_type',
    'payload',
])]
class FinancialObligationEvent extends Model
{
    /** @use HasFactory<FinancialObligationEventFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $event): void {
            $event->uuid ??= (string) Str::uuid();
        });

        static::updating(function (): never {
            throw new LogicException('Financial Obligation events are immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException('Financial Obligation events preserve financial history.');
        });
    }

    /** @return BelongsTo<FinancialObligation, $this> */
    public function obligation(): BelongsTo
    {
        return $this->belongsTo(FinancialObligation::class, 'financial_obligation_id');
    }

    /** @return BelongsTo<Settlement, $this> */
    public function settlement(): BelongsTo
    {
        return $this->belongsTo(Settlement::class);
    }

    /** @return BelongsTo<JournalEntry, $this> */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /** @return BelongsTo<Actor, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(Actor::class);
    }

    protected function casts(): array
    {
        return [
            'event_type' => FinancialObligationEventType::class,
            'payload' => 'array',
        ];
    }
}
