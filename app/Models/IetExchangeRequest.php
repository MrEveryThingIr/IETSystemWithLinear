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
    'external_unit_code',
    'external_amount_minor',
    'valuation_quote_id',
    'iet_amount',
    'status',
    'external_reference',
    'note',
    'reviewed_by_user_id',
    'reviewed_at',
    'review_note',
    'journal_entry_id',
])]
class IetExchangeRequest extends Model
{
    private bool $applyingLifecycle = false;

    protected $attributes = ['status' => IetExchangeStatus::Pending->value];

    protected static function booted(): void
    {
        static::creating(function (self $request): void {
            $request->uuid ??= (string) Str::uuid();
        });

        static::updating(function (self $request): void {
            if (! $request->applyingLifecycle) {
                throw new LogicException('IET Exchange lifecycle changes require dedicated Actions.');
            }

            if ($request->isDirty([
                'uuid',
                'user_id',
                'direction',
                'external_unit_code',
                'external_amount_minor',
                'valuation_quote_id',
                'iet_amount',
                'external_reference',
                'note',
            ])) {
                throw new LogicException('IET Exchange request facts are immutable.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('IET Exchange history cannot be deleted.');
        });
    }

    public function confirm(User $reviewer, JournalEntry $entry): void
    {
        $this->saveLifecycle([
            'status' => IetExchangeStatus::Confirmed,
            'reviewed_by_user_id' => $reviewer->id,
            'reviewed_at' => now(),
            'journal_entry_id' => $entry->id,
            'review_note' => null,
        ]);
    }

    public function reject(User $reviewer, ?string $note): void
    {
        $this->saveLifecycle([
            'status' => IetExchangeStatus::Rejected,
            'reviewed_by_user_id' => $reviewer->id,
            'reviewed_at' => now(),
            'review_note' => filled($note) ? trim((string) $note) : null,
        ]);
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

    protected function casts(): array
    {
        return [
            'direction' => IetExchangeDirection::class,
            'status' => IetExchangeStatus::class,
            'external_amount_minor' => 'integer',
            'iet_amount' => 'integer',
            'reviewed_at' => 'immutable_datetime',
        ];
    }

    /** @param array<string, mixed> $attributes */
    private function saveLifecycle(array $attributes): void
    {
        abort_unless($this->status === IetExchangeStatus::Pending, 422, 'Only pending IET Exchange requests may be reviewed.');

        $this->applyingLifecycle = true;

        try {
            $this->forceFill($attributes)->save();
        } finally {
            $this->applyingLifecycle = false;
        }
    }
}
