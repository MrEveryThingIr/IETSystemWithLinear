<?php

namespace App\Models\Bookkeeping;

use App\Models\Actor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** Append-only history: who did what to a book or entry, when and from where. */
#[Fillable(['ledger_id', 'transaction_id', 'actor_id', 'on_behalf_of_actor_id', 'action', 'before', 'after', 'ip', 'user_agent', 'created_at'])]
class AuditLog extends Model
{
    protected $table = 'bk_audit_logs';

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'after' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $log): void {
            $log->created_at ??= now();
        });

        static::updating(function (): never {
            throw new LogicException('The audit log is append-only.');
        });

        static::deleting(function (): never {
            throw new LogicException('The audit log is append-only.');
        });
    }

    /** @return BelongsTo<Ledger, $this> */
    public function ledger(): BelongsTo
    {
        return $this->belongsTo(Ledger::class);
    }

    /** @return BelongsTo<Transaction, $this> */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /** @return BelongsTo<Actor, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(Actor::class);
    }
}
