<?php

namespace App\Models;

use App\JournalEntryKind;
use Database\Factories\JournalEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'uuid',
    'ledger_id',
    'kind',
    'occurred_on',
    'description',
    'source_type',
    'source_uuid',
    'idempotency_key',
    'reverses_entry_id',
    'correction_of_entry_id',
    'created_by_actor_id',
    'acting_user_id',
    'posted_at',
])]
class JournalEntry extends Model
{
    /** @use HasFactory<JournalEntryFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $entry): void {
            $entry->uuid ??= (string) Str::uuid();
            $entry->posted_at ??= now();
        });

        static::updating(function (): never {
            throw new LogicException('Posted journal entries are immutable. Reverse or correct them instead.');
        });

        static::deleting(function (): never {
            throw new LogicException('Posted journal entries cannot be deleted.');
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<Ledger, $this> */
    public function ledger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actingUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acting_user_id');
    }

    /** @return BelongsTo<Actor, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'created_by_actor_id');
    }

    /** @return BelongsTo<JournalEntry, $this> */
    public function reverses(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_entry_id');
    }

    /** @return BelongsTo<JournalEntry, $this> */
    public function correctionOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'correction_of_entry_id');
    }

    /** @return HasMany<JournalEntry, $this> */
    public function reversals(): HasMany
    {
        return $this->hasMany(self::class, 'reverses_entry_id');
    }

    /** @return HasMany<JournalEntry, $this> */
    public function corrections(): HasMany
    {
        return $this->hasMany(self::class, 'correction_of_entry_id');
    }

    /** @return HasMany<JournalLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    protected function casts(): array
    {
        return [
            'kind' => JournalEntryKind::class,
            'occurred_on' => 'date:Y-m-d',
            'posted_at' => 'datetime',
        ];
    }
}
