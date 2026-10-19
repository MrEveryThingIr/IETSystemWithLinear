<?php

namespace App\Models\Bookkeeping;

use App\Enums\Bookkeeping\ConfirmationDecision;
use App\Models\Actor;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** One party's approval or rejection of an entry, with who decided and from where. */
#[Fillable(['transaction_id', 'party_id', 'actor_id', 'on_behalf_of_actor_id', 'decision', 'note', 'decided_at', 'ip', 'user_agent'])]
class Confirmation extends Model
{
    protected $table = 'bk_confirmations';

    protected function casts(): array
    {
        return [
            'decision' => ConfirmationDecision::class,
            'decided_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('A decision, once made, is kept as it was.');
        });

        static::deleting(function (): never {
            throw new LogicException('Decisions are never deleted.');
        });
    }

    /** @return BelongsTo<Transaction, $this> */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /** @return BelongsTo<LedgerParty, $this> */
    public function party(): BelongsTo
    {
        return $this->belongsTo(LedgerParty::class, 'party_id');
    }

    /** @return BelongsTo<Actor, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(Actor::class);
    }
}
