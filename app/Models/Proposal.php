<?php

namespace App\Models;

use App\ProposalStatus;
use Database\Factories\ProposalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use LogicException;

#[Fillable(['uuid', 'title', 'relationship_id', 'created_by_actor_id', 'status'])]
class Proposal extends Model
{
    /** @use HasFactory<ProposalFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => ProposalStatus::Negotiating->value,
    ];

    private bool $applyingLifecycle = false;

    protected static function booted(): void
    {
        static::creating(function (self $proposal): void {
            $proposal->uuid ??= (string) Str::uuid();
        });

        static::updating(function (self $proposal): void {
            if ($proposal->isDirty(['uuid', 'relationship_id', 'created_by_actor_id'])) {
                throw new LogicException('Proposal identity, source Relationship and creator are immutable.');
            }

            if ($proposal->isDirty('status') && ! $proposal->applyingLifecycle) {
                throw new LogicException('Proposal lifecycle changes require a dedicated Action.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Proposals preserve negotiation history and cannot be deleted.');
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<Relationship, $this> */
    public function relationship(): BelongsTo
    {
        return $this->belongsTo(Relationship::class);
    }

    /** @return BelongsTo<Actor, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'created_by_actor_id');
    }

    /** @return HasMany<ProposalParty, $this> */
    public function parties(): HasMany
    {
        return $this->hasMany(ProposalParty::class);
    }

    /** @return HasOne<ProposalContext, $this> */
    public function contextBinding(): HasOne
    {
        return $this->hasOne(ProposalContext::class);
    }

    /** @return HasMany<ProposalVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(ProposalVersion::class);
    }

    /** @return HasMany<ProposalEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(ProposalEvent::class)->orderBy('id');
    }

    public function currentVersionRecord(): ?ProposalVersion
    {
        return $this->versions()->latest('version')->first();
    }

    public function applyStatus(ProposalStatus $status): void
    {
        if ($this->status !== ProposalStatus::Negotiating) {
            throw new LogicException('Only a negotiating Proposal may transition lifecycle.');
        }

        if (! in_array($status, [
            ProposalStatus::Accepted,
            ProposalStatus::Rejected,
            ProposalStatus::Cancelled,
        ], true)) {
            throw new LogicException('Proposal lifecycle transition is invalid.');
        }

        $this->applyingLifecycle = true;

        try {
            $this->forceFill(['status' => $status])->save();
        } finally {
            $this->applyingLifecycle = false;
        }
    }

    protected function casts(): array
    {
        return ['status' => ProposalStatus::class];
    }
}
