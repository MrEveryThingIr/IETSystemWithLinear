<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'uuid',
    'sender_user_id',
    'receiver_user_id',
    'iet_amount_minor',
    'rate_version_id',
    'usd_reference_minor',
    'source_type',
    'source_uuid',
    'sender_journal_entry_id',
    'receiver_journal_entry_id',
    'description',
])]
class IetInternalTransfer extends Model
{
    protected static function booted(): void
    {
        static::creating(function (self $transfer): void {
            $transfer->uuid ??= (string) Str::uuid();
        });

        static::updating(function (): never {
            throw new LogicException('IET internal transfers are immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException('IET internal transfer history cannot be deleted.');
        });
    }

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_user_id');
    }

    /** @return BelongsTo<IetRateVersion, $this> */
    public function rateVersion(): BelongsTo
    {
        return $this->belongsTo(IetRateVersion::class, 'rate_version_id');
    }

    protected function casts(): array
    {
        return [
            'iet_amount_minor' => 'integer',
            'usd_reference_minor' => 'integer',
        ];
    }
}
