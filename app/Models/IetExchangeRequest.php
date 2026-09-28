<?php

namespace App\Models;

use App\IetExchangeDirection;
use App\IetExchangeStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'uuid',
    'user_id',
    'direction',
    'rate_version_id',
    'fiat_unit_id',
    'fiat_amount_minor',
    'iet_amount_minor',
    'status',
    'external_reference',
    'note',
    'reviewed_by_user_id',
    'reviewed_at',
    'rejection_note',
    'journal_entry_id',
])]
class IetExchangeRequest extends Model
{
    protected $attributes = ['status' => IetExchangeStatus::Pending->value];

    private bool $applyingLifecycle = false;

    protected static function booted(): void
    {
        static::creating(function (self $request): void {
            $request->uuid ??= (string) Str::uuid();
        });

        static::updating(function (self $request): void {
            if (! $request->applyingLifecycle) {
                throw new LogicException('IET exchange lifecycle changes require dedicated Actions.');
            }

            if ($request->isDirty([
                'uuid',
                'user_id',
                'direction',
                'rate_version_id',
                'fiat_unit_id',
                'fiat_amount_minor',
                'iet_amount_minor',
            ])) {
                throw new LogicException('IET exchange request facts are immutable.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('IET exchange history cannot be deleted.');
        });
    }

    public function confirm(User $reviewer, JournalEntry $entry): void
    {
        $this->saveLifecycle([
            'status' => IetExchangeStatus::Confirmed,
            'reviewed_by_user_id' => $reviewer->id,
            'reviewed_at' => now(),
            'journal_entry_id' => $entry->id,
        ]);
    }

    public function reject(User $reviewer, string $note): void
    {
        $this->saveLifecycle([
            'status' => IetExchangeStatus::Rejected,
            'reviewed_by_user_id' => $reviewer->id,
            'reviewed_at' => now(),
            'rejection_note' => $note,
        ]);
    }

    public function cancel(): void
    {
        $this->saveLifecycle(['status' => IetExchangeStatus::Cancelled]);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<IetRateVersion, $this> */
    public function rateVersion(): BelongsTo
    {
        return $this->belongsTo(IetRateVersion::class, 'rate_version_id');
    }

    /** @return BelongsTo<MonetaryUnit, $this> */
    public function fiatUnit(): BelongsTo
    {
        return $this->belongsTo(MonetaryUnit::class, 'fiat_unit_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    /** @return BelongsTo<JournalEntry, $this> */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    private function saveLifecycle(array $attributes): void
    {
        if ($this->status !== IetExchangeStatus::Pending) {
            throw new LogicException('Only pending IET exchange requests may change state.');
        }

        $this->applyingLifecycle = true;

        try {
            $this->forceFill($attributes)->save();
        } finally {
            $this->applyingLifecycle = false;
        }
    }

    protected function casts(): array
    {
        return [
            'direction' => IetExchangeDirection::class,
            'status' => IetExchangeStatus::class,
            'fiat_amount_minor' => 'integer',
            'iet_amount_minor' => 'integer',
            'reviewed_at' => 'immutable_datetime',
        ];
    }
}
