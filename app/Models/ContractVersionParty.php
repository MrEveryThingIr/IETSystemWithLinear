<?php

namespace App\Models;

use Database\Factories\ContractVersionPartyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'uuid',
    'contract_version_id',
    'actor_id',
    'role',
    'required',
    'source_proposal_party_id',
])]
class ContractVersionParty extends Model
{
    /** @use HasFactory<ContractVersionPartyFactory> */
    use HasFactory;

    protected $attributes = ['required' => true];

    protected static function booted(): void
    {
        static::creating(function (self $party): void {
            $party->uuid ??= (string) Str::uuid();
        });

        static::updating(function (): never {
            throw new LogicException('ContractVersion parties are immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException('ContractVersion parties preserve authoritative party history.');
        });
    }

    /** @return BelongsTo<ContractVersion, $this> */
    public function contractVersion(): BelongsTo
    {
        return $this->belongsTo(ContractVersion::class);
    }

    /** @return BelongsTo<Actor, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(Actor::class);
    }

    /** @return BelongsTo<ProposalParty, $this> */
    public function sourceProposalParty(): BelongsTo
    {
        return $this->belongsTo(ProposalParty::class, 'source_proposal_party_id');
    }

    /** @return HasOne<ContractAcceptance, $this> */
    public function acceptance(): HasOne
    {
        return $this->hasOne(ContractAcceptance::class);
    }

    protected function casts(): array
    {
        return ['required' => 'boolean'];
    }
}
