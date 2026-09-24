<?php

namespace App\Models;

use Database\Factories\ContractAcceptanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'uuid',
    'contract_version_party_id',
    'accepted_by_user_id',
    'accepted_at',
])]
class ContractAcceptance extends Model
{
    /** @use HasFactory<ContractAcceptanceFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $acceptance): void {
            $acceptance->uuid ??= (string) Str::uuid();
        });

        static::updating(function (): never {
            throw new LogicException('Contract acceptances are immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException('Contract acceptances preserve exact-version acceptance evidence.');
        });
    }

    /** @return BelongsTo<ContractVersionParty, $this> */
    public function party(): BelongsTo
    {
        return $this->belongsTo(ContractVersionParty::class, 'contract_version_party_id');
    }

    /** @return BelongsTo<User, $this> */
    public function acceptedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by_user_id');
    }

    protected function casts(): array
    {
        return ['accepted_at' => 'datetime'];
    }
}
