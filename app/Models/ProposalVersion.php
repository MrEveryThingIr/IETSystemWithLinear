<?php

namespace App\Models;

use App\Support\Deals\DealTerms;
use Database\Factories\ProposalVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'uuid',
    'proposal_id',
    'version',
    'terms_content_revision_id',
    'terms_data',
    'terms_hash',
    'proposed_by_actor_id',
    'note',
    'proposed_at',
])]
class ProposalVersion extends Model
{
    /** @use HasFactory<ProposalVersionFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $version): void {
            $version->uuid ??= (string) Str::uuid();
        });

        static::updating(function (): never {
            throw new LogicException('Proposal versions are immutable.');
        });

        static::deleting(function (): never {
            throw new LogicException('Proposal versions preserve negotiation history and cannot be deleted.');
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<Proposal, $this> */
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }

    /** @return BelongsTo<SpaceContentRevision, $this> */
    public function termsRevision(): BelongsTo
    {
        return $this->belongsTo(SpaceContentRevision::class, 'terms_content_revision_id');
    }

    /** @return BelongsTo<Actor, $this> */
    public function proposedBy(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'proposed_by_actor_id');
    }

    /** @return HasMany<ProposalDecision, $this> */
    public function decisions(): HasMany
    {
        return $this->hasMany(ProposalDecision::class);
    }

    /** @return HasOne<Contract, $this> */
    public function derivedContract(): HasOne
    {
        return $this->hasOne(Contract::class, 'source_proposal_version_id');
    }

    /** True when the stored structured Terms still hash to the value sealed at creation (null for pre-M3 versions). */
    public function termsIntegrityOk(): ?bool
    {
        return $this->terms_data === null ? null : hash_equals((string) $this->terms_hash, DealTerms::hash($this->terms_data));
    }

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'terms_data' => 'array',
            'proposed_at' => 'datetime',
        ];
    }
}
