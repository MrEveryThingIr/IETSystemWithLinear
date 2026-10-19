<?php

namespace App\Models\Bookkeeping;

use App\Enums\Bookkeeping\DisputeOutcome;
use App\Enums\Bookkeeping\DisputeStatus;
use App\Models\Actor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

#[Fillable(['uuid', 'transaction_id', 'raised_by_actor_id', 'reason', 'status', 'outcome', 'resolution_note', 'resolved_by_actor_id', 'resolved_at'])]
class Dispute extends Model
{
    protected $table = 'bk_disputes';

    protected function casts(): array
    {
        return [
            'status' => DisputeStatus::class,
            'outcome' => DisputeOutcome::class,
            'resolved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $dispute): void {
            $dispute->uuid ??= (string) Str::uuid();
        });

        static::updating(function (self $dispute): void {
            if ($dispute->getOriginal('status') === DisputeStatus::Resolved->value
                || $dispute->getOriginal('status') === DisputeStatus::Resolved) {
                throw new LogicException('A resolved dispute is kept as it was.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Disputes are never deleted.');
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<Transaction, $this> */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /** @return BelongsTo<Actor, $this> */
    public function raisedBy(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'raised_by_actor_id');
    }

    /** @return BelongsTo<Actor, $this> */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'resolved_by_actor_id');
    }
}
