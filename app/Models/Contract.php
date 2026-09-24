<?php

namespace App\Models;

use App\ContractStatus;
use App\ContractVersionStatus;
use Database\Factories\ContractFactory;
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
    'title',
    'relationship_id',
    'source_proposal_version_id',
    'created_by_actor_id',
    'status',
])]
class Contract extends Model
{
    /** @use HasFactory<ContractFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => ContractStatus::Pending->value,
    ];

    private bool $applyingLifecycle = false;

    protected static function booted(): void
    {
        static::creating(function (self $contract): void {
            $contract->uuid ??= (string) Str::uuid();
        });

        static::updating(function (self $contract): void {
            if ($contract->isDirty([
                'uuid',
                'relationship_id',
                'source_proposal_version_id',
                'created_by_actor_id',
            ])) {
                throw new LogicException('Contract identity and provenance are immutable.');
            }

            if ($contract->isDirty('status') && ! $contract->applyingLifecycle) {
                throw new LogicException('Contract lifecycle changes require a dedicated Action.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Contracts preserve authoritative agreement history and cannot be deleted.');
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

    /** @return BelongsTo<ProposalVersion, $this> */
    public function sourceProposalVersion(): BelongsTo
    {
        return $this->belongsTo(ProposalVersion::class, 'source_proposal_version_id');
    }

    /** @return BelongsTo<Actor, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'created_by_actor_id');
    }

    /** @return HasOne<ContractContext, $this> */
    public function contextBinding(): HasOne
    {
        return $this->hasOne(ContractContext::class);
    }

    /** @return HasMany<ContractVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(ContractVersion::class);
    }

    /** @return HasMany<ContractEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(ContractEvent::class)->orderBy('id');
    }

    public function activeVersionRecord(): ?ContractVersion
    {
        return $this->versions()
            ->where('status', ContractVersionStatus::Active->value)
            ->first();
    }

    public function pendingVersionRecord(): ?ContractVersion
    {
        return $this->versions()
            ->whereIn('status', [
                ContractVersionStatus::Proposed->value,
                ContractVersionStatus::Accepted->value,
            ])
            ->latest('version')
            ->first();
    }

    public function activate(): void
    {
        if ($this->status !== ContractStatus::Pending) {
            return;
        }

        $this->applyingLifecycle = true;

        try {
            $this->forceFill(['status' => ContractStatus::Active])->save();
        } finally {
            $this->applyingLifecycle = false;
        }
    }

    protected function casts(): array
    {
        return ['status' => ContractStatus::class];
    }
}
