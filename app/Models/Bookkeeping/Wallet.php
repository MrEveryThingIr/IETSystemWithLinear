<?php

namespace App\Models\Bookkeeping;

use App\Enums\Bookkeeping\WalletKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

/** Where money sits for the owner of a personal book. Private to that person. */
#[Fillable(['uuid', 'ledger_id', 'name', 'kind', 'masked_identifier', 'opening_balance', 'archived_at'])]
class Wallet extends Model
{
    protected $table = 'bk_wallets';

    protected function casts(): array
    {
        return [
            'kind' => WalletKind::class,
            'opening_balance' => 'integer',
            'archived_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $wallet): void {
            $wallet->uuid ??= (string) Str::uuid();
        });

        static::deleting(function (): never {
            throw new LogicException('Wallets keep their history; archive them instead.');
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
}
