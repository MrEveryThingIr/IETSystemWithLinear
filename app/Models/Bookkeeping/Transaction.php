<?php

namespace App\Models\Bookkeeping;

use App\Enums\Bookkeeping\AdjustmentDirection;
use App\Enums\Bookkeeping\EntryOrigin;
use App\Enums\Bookkeeping\TransactionStatus;
use App\Enums\Bookkeeping\TransactionType;
use App\Models\Actor;
use App\Models\Asset;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

/**
 * One recorded fact in a book. What was recorded never changes; only its
 * status moves through TransactionStatus (via transitionTo), so every number
 * stays traceable. Corrections are new entries.
 *
 * Direction convention: from_party owes / pays / gives value to to_party.
 * Obligation: from = debtor, to = creditor. Payment: from = payer, to = payee.
 * Refund: from = the one giving money back, to = the one receiving it.
 * Write-off: from = debtor, to = creditor (the creditor forgives).
 *
 * @property Carbon $occurred_on
 * @property Carbon|null $due_on
 */
#[Fillable([
    'uuid',
    'ledger_id',
    'type',
    'status',
    'origin',
    'source_type',
    'source_uuid',
    'source_version',
    'amount',
    'direction',
    'occurred_on',
    'due_on',
    'description',
    'from_party_id',
    'to_party_id',
    'wallet_id',
    'counter_wallet_id',
    'category_id',
    'counterparty_name',
    'related_transaction_id',
    'recorded_by_actor_id',
    'on_behalf_of_actor_id',
    'idempotency_key',
    'confirmed_at',
    'voided_at',
    'void_reason',
    'version',
    'meta',
])]
class Transaction extends Model
{
    protected $table = 'bk_transactions';

    private bool $transitioning = false;

    /** Fields that may change, and only through transitionTo(). */
    private const LIFECYCLE = ['status', 'confirmed_at', 'voided_at', 'void_reason', 'version', 'updated_at'];

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'status' => TransactionStatus::class,
            'origin' => EntryOrigin::class,
            'source_version' => 'integer',
            'direction' => AdjustmentDirection::class,
            'amount' => 'integer',
            'occurred_on' => 'date',
            'due_on' => 'date',
            'confirmed_at' => 'datetime',
            'voided_at' => 'datetime',
            'version' => 'integer',
            'meta' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $transaction): void {
            $transaction->uuid ??= (string) Str::uuid();
            $transaction->version ??= 1;
            $transaction->origin ??= EntryOrigin::Manual;

            if ((int) $transaction->amount <= 0) {
                throw new LogicException('Amounts must be positive whole toman.');
            }
        });

        static::updating(function (self $transaction): void {
            $changed = array_keys($transaction->getDirty());

            if (array_diff($changed, self::LIFECYCLE) !== []) {
                throw new LogicException('Recorded entries never change; record a correction instead.');
            }

            if (! $transaction->transitioning) {
                throw new LogicException('Entry status changes go through transitionTo().');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Bookkeeping entries are never deleted.');
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * The only way to change an entry: a valid status step, with optimistic
     * locking on `version` so two people acting at once cannot both win.
     *
     * @param  array<string, mixed>  $attributes  confirmed_at / voided_at / void_reason
     */
    public function transitionTo(TransactionStatus $next, array $attributes = []): void
    {
        if (! $this->status->canTransitionTo($next)) {
            throw new LogicException("An entry cannot go from {$this->status->value} to {$next->value}.");
        }

        $expectedVersion = (int) $this->version;
        $this->transitioning = true;

        try {
            $updated = DB::table($this->getTable())
                ->where('id', $this->id)
                ->where('version', $expectedVersion)
                ->where('status', $this->status->value)
                ->update([
                    'status' => $next->value,
                    'version' => $expectedVersion + 1,
                    'updated_at' => now(),
                    ...$attributes,
                ]);
            // The query builder does not cast; keep the model in step.

            if ($updated !== 1) {
                throw new LogicException('This entry was changed by someone else. Reload and try again.');
            }

            $this->forceFill(['status' => $next, 'version' => $expectedVersion + 1, ...$attributes]);
            $this->syncOriginal();
        } finally {
            $this->transitioning = false;
        }
    }

    public function counts(): bool
    {
        return $this->status === TransactionStatus::Confirmed;
    }

    /** @return BelongsTo<Ledger, $this> */
    public function ledger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class);
    }

    /** @return BelongsTo<LedgerParty, $this> */
    public function fromParty(): BelongsTo
    {
        return $this->belongsTo(LedgerParty::class, 'from_party_id');
    }

    /** @return BelongsTo<LedgerParty, $this> */
    public function toParty(): BelongsTo
    {
        return $this->belongsTo(LedgerParty::class, 'to_party_id');
    }

    /** @return BelongsTo<Wallet, $this> */
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    /** @return BelongsTo<Wallet, $this> */
    public function counterWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'counter_wallet_id');
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<self, $this> */
    public function related(): BelongsTo
    {
        return $this->belongsTo(self::class, 'related_transaction_id');
    }

    /** @return BelongsTo<Actor, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'recorded_by_actor_id');
    }

    /** @return HasMany<Allocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(Allocation::class, 'transaction_id');
    }

    /**
     * Allocations of payments, refunds, write-offs and in-kind settlements to this debt.
     *
     * @return HasMany<Allocation, $this>
     */
    public function settledBy(): HasMany
    {
        return $this->hasMany(Allocation::class, 'obligation_id');
    }

    /** @return HasMany<self, $this> */
    public function adjustments(): HasMany
    {
        return $this->hasMany(self::class, 'related_transaction_id');
    }

    /** @return HasMany<Confirmation, $this> */
    public function confirmations(): HasMany
    {
        return $this->hasMany(Confirmation::class);
    }

    /** @return HasMany<Dispute, $this> */
    public function disputes(): HasMany
    {
        return $this->hasMany(Dispute::class);
    }

    /** @return BelongsToMany<Asset, $this> */
    public function assets(): BelongsToMany
    {
        return $this->belongsToMany(Asset::class, 'bk_transaction_assets', 'transaction_id', 'asset_id')
            ->withPivot(['kind', 'added_by_actor_id'])
            ->withTimestamps();
    }
}
