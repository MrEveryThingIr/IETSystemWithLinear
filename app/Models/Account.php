<?php

namespace App\Models;

use App\AccountType;
use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

#[Fillable(['uuid','ledger_id','code','name','type','system_key','status','created_by_actor_id'])]
class Account extends Model
{
    /** @use HasFactory<AccountFactory> */
    use HasFactory;

    protected $attributes = ['status' => 'active'];

    protected static function booted(): void
    {
        static::creating(function (self $account): void {
            $account->uuid ??= (string) Str::uuid();
        });

        static::updating(function (self $account): void {
            if ($account->isDirty(['uuid','ledger_id','code','type','system_key','created_by_actor_id'])) {
                throw new LogicException('Account ledger, code, type and provenance are immutable.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Accounts preserve accounting history and cannot be deleted.');
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

    /** @return BelongsTo<Actor, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'created_by_actor_id');
    }

    /** @return HasMany<JournalLine, $this> */
    public function journalLines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    protected function casts(): array
    {
        return ['type' => AccountType::class];
    }
}
