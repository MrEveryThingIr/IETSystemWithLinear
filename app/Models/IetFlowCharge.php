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
    'rate_version_id',
    'usd_reference_minor',
    'iet_amount_minor',
    'source_type',
    'source_uuid',
    'description',
    'journal_entry_id',
])]
class IetFlowCharge extends Model
{
    protected static function booted(): void
    {
        static::creating(function (self $charge): void {
            $charge->uuid ??= (string) Str::uuid();
        });

        static::updating(function (): never {
            throw new LogicException('IET flow charges are immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException('IET flow charge history cannot be deleted.');
        });
    }

    /** @return BelongsTo<IetRateVersion, $this> */
    public function rateVersion(): BelongsTo
    {
        return $this->belongsTo(IetRateVersion::class, 'rate_version_id');
    }

    /** @return BelongsTo<JournalEntry, $this> */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    protected function casts(): array
    {
        return [
            'usd_reference_minor' => 'integer',
            'iet_amount_minor' => 'integer',
        ];
    }
}
