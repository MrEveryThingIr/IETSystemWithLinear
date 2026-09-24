<?php

namespace App\Models;

use Database\Factories\JournalLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'uuid',
    'journal_entry_id',
    'account_id',
    'debit_minor',
    'credit_minor',
    'memo',
])]
class JournalLine extends Model
{
    /** @use HasFactory<JournalLineFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $line): void {
            $line->uuid ??= (string) Str::uuid();
            $debit = (int) $line->debit_minor;
            $credit = (int) $line->credit_minor;

            if (($debit > 0) === ($credit > 0)) {
                throw new LogicException('A journal line must contain exactly one positive debit or credit amount.');
            }

            $entryLedgerId = JournalEntry::query()->whereKey($line->journal_entry_id)->value('ledger_id');
            $accountLedgerId = Account::query()->whereKey($line->account_id)->value('ledger_id');

            if ((int) $entryLedgerId !== (int) $accountLedgerId) {
                throw new LogicException('Journal line Account must belong to the Journal Entry Ledger.');
            }
        });

        static::updating(function (): never {
            throw new LogicException('Journal lines are immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException('Journal lines cannot be deleted.');
        });
    }

    /** @return BelongsTo<JournalEntry, $this> */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    protected function casts(): array
    {
        return [
            'debit_minor' => 'integer',
            'credit_minor' => 'integer',
        ];
    }
}
