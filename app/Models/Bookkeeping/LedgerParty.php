<?php

namespace App\Models\Bookkeeping;

use App\Enums\Bookkeeping\PartyRole;
use App\Enums\Bookkeeping\PartyStatus;
use App\Models\Actor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

/**
 * A person in a book. The Actor may be a user or a name-only person without
 * an account (for example a day worker), who can be recorded but cannot
 * confirm anything.
 */
#[Fillable([
    'uuid',
    'ledger_id',
    'actor_id',
    'display_name',
    'contact_hint',
    'role',
    'status',
    'added_by_actor_id',
    'joined_at',
    'left_at',
])]
class LedgerParty extends Model
{
    protected $table = 'bk_ledger_parties';

    protected function casts(): array
    {
        return [
            'role' => PartyRole::class,
            'status' => PartyStatus::class,
            'joined_at' => 'datetime',
            'left_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $party): void {
            $party->uuid ??= (string) Str::uuid();
            $party->joined_at ??= now();
        });

        static::updating(function (self $party): void {
            if ($party->isDirty(['uuid', 'ledger_id', 'actor_id', 'added_by_actor_id'])) {
                throw new LogicException('A book party keeps its person and book.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Book parties keep their history; mark them as left instead.');
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** A person with an account who can sign in and confirm. */
    public function canConfirm(): bool
    {
        $this->loadMissing('actor.user');

        return $this->status === PartyStatus::Active
            && $this->actor->user !== null
            && $this->actor->user->status === 'active';
    }

    public function label(): string
    {
        $this->loadMissing('actor.user', 'actor.profile');

        return $this->display_name
            ?: ($this->actor->profile->display_name ?? null)
            ?: ($this->actor->user->username ?? '#'.$this->actor_id);
    }

    /** @return BelongsTo<Ledger, $this> */
    public function ledger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class);
    }

    /** @return BelongsTo<Actor, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(Actor::class);
    }
}
