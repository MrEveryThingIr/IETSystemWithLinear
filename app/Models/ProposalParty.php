<?php

namespace App\Models;

use Database\Factories\ProposalPartyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

#[Fillable(['uuid', 'proposal_id', 'actor_id', 'role', 'required', 'added_by_actor_id'])]
class ProposalParty extends Model
{
    /** @use HasFactory<ProposalPartyFactory> */
    use HasFactory;

    protected $attributes = ['required' => true];

    protected static function booted(): void
    {
        static::creating(function (self $party): void {
            $party->uuid ??= (string) Str::uuid();
        });

        static::updating(function (): never {
            throw new LogicException('Proposal parties are immutable for this Proposal.');
        });

        static::deleting(function (): never {
            throw new LogicException('Proposal parties preserve negotiation history and cannot be deleted.');
        });
    }

    /** @return BelongsTo<Proposal, $this> */
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }

    /** @return BelongsTo<Actor, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(Actor::class);
    }

    /** @return BelongsTo<Actor, $this> */
    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'added_by_actor_id');
    }

    /** @return HasMany<ProposalDecision, $this> */
    public function decisions(): HasMany
    {
        return $this->hasMany(ProposalDecision::class);
    }

    protected function casts(): array
    {
        return ['required' => 'boolean'];
    }
}
