<?php

namespace App\Models\Bookkeeping;

use App\Enums\Bookkeeping\LedgerKind;
use App\Enums\Bookkeeping\LedgerStatus;
use App\Enums\Bookkeeping\PartyStatus;
use App\Models\Actor;
use App\Models\Context;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

/**
 * A book: personal (one person's own money), shared (between people) or
 * project. Every book has its own Context so evidence files and the book's
 * chat reuse the platform's private storage and conversation.
 */
#[Fillable([
    'uuid',
    'kind',
    'name',
    'description',
    'currency',
    'context_id',
    'owner_actor_id',
    'status',
    'archived_at',
    'meta',
])]
class Ledger extends Model
{
    protected $table = 'bk_ledgers';

    protected $attributes = [
        'currency' => 'IRT',
        'status' => 'active',
    ];

    protected function casts(): array
    {
        return [
            'kind' => LedgerKind::class,
            'status' => LedgerStatus::class,
            'archived_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $ledger): void {
            $ledger->uuid ??= (string) Str::uuid();
        });

        static::updating(function (self $ledger): void {
            if ($ledger->isDirty(['uuid', 'kind', 'context_id', 'owner_actor_id', 'currency'])) {
                throw new LogicException('A book\'s kind, owner, place and currency cannot change.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Books keep their history; archive them instead.');
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function isPersonal(): bool
    {
        return $this->kind === LedgerKind::Personal;
    }

    /** @return BelongsTo<Context, $this> */
    public function context(): BelongsTo
    {
        return $this->belongsTo(Context::class);
    }

    /** @return BelongsTo<Actor, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'owner_actor_id');
    }

    /** @return HasMany<LedgerParty, $this> */
    public function parties(): HasMany
    {
        return $this->hasMany(LedgerParty::class);
    }

    /** @return HasMany<LedgerParty, $this> */
    public function activeParties(): HasMany
    {
        return $this->parties()->where('status', PartyStatus::Active->value);
    }

    /** @return HasMany<Transaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /** @return HasMany<Wallet, $this> */
    public function wallets(): HasMany
    {
        return $this->hasMany(Wallet::class);
    }

    /** @return HasMany<Category, $this> */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function partyFor(Actor|int $actor): ?LedgerParty
    {
        $actorId = $actor instanceof Actor ? $actor->id : $actor;

        return $this->parties()
            ->where('actor_id', $actorId)
            ->where('status', PartyStatus::Active->value)
            ->first();
    }
}
