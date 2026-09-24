<?php

namespace App\Models;

use Database\Factories\LedgerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

#[Fillable(['uuid','context_id','monetary_unit_id','key','name','status','created_by_actor_id'])]
class Ledger extends Model
{
    /** @use HasFactory<LedgerFactory> */
    use HasFactory;

    protected $attributes = ['key' => 'main', 'status' => 'active'];

    protected static function booted(): void
    {
        static::creating(function (self $ledger): void {
            $ledger->uuid ??= (string) Str::uuid();
        });

        static::updating(function (self $ledger): void {
            if ($ledger->isDirty(['uuid','context_id','monetary_unit_id','key','created_by_actor_id'])) {
                throw new LogicException('Ledger identity, Context, unit and creator are immutable.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Ledgers preserve accounting history and cannot be deleted.');
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<Context, $this> */
    public function context(): BelongsTo
    {
        return $this->belongsTo(Context::class);
    }

    /** @return BelongsTo<MonetaryUnit, $this> */
    public function monetaryUnit(): BelongsTo
    {
        return $this->belongsTo(MonetaryUnit::class);
    }

    /** @return BelongsTo<Actor, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'created_by_actor_id');
    }

    /** @return HasMany<Account, $this> */
    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    /** @return HasMany<JournalEntry, $this> */
    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }
}
