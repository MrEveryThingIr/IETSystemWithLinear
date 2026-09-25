<?php

namespace App\Models;

use App\ContractVersionStatus;
use Carbon\CarbonInterface;
use Database\Factories\ContractVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'uuid',
    'contract_id',
    'version',
    'terms_content_revision_id',
    'supersedes_version_id',
    'proposed_by_actor_id',
    'status',
    'effective_from',
    'effective_timezone',
    'effective_until',
    'note',
    'proposed_at',
    'accepted_at',
    'activated_at',
    'superseded_at',
])]
class ContractVersion extends Model
{
    /** @use HasFactory<ContractVersionFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => ContractVersionStatus::Proposed->value,
    ];

    private bool $applyingLifecycle = false;

    protected static function booted(): void
    {
        static::creating(function (self $version): void {
            $version->uuid ??= (string) Str::uuid();
        });

        static::updating(function (self $version): void {
            if (! $version->applyingLifecycle) {
                throw new LogicException('Contract versions are immutable outside dedicated lifecycle Actions.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Contract versions preserve authoritative agreement history and cannot be deleted.');
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<Contract, $this> */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /** @return BelongsTo<SpaceContentRevision, $this> */
    public function termsRevision(): BelongsTo
    {
        return $this->belongsTo(SpaceContentRevision::class, 'terms_content_revision_id');
    }

    /** @return BelongsTo<ContractVersion, $this> */
    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_version_id');
    }

    /** @return BelongsTo<Actor, $this> */
    public function proposedBy(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'proposed_by_actor_id');
    }

    /** @return HasMany<ContractVersionParty, $this> */
    public function parties(): HasMany
    {
        return $this->hasMany(ContractVersionParty::class);
    }

    /** @return HasMany<Commitment, $this> */
    public function commitments(): HasMany
    {
        return $this->hasMany(Commitment::class);
    }

    /** @return HasMany<FinancialObligation, $this> */
    public function financialObligations(): HasMany
    {
        return $this->hasMany(FinancialObligation::class);
    }

    public function markAccepted(CarbonInterface $at): void
    {
        if ($this->status !== ContractVersionStatus::Proposed) {
            throw new LogicException('Only a proposed ContractVersion may become accepted.');
        }

        $this->saveLifecycle([
            'status' => ContractVersionStatus::Accepted,
            'accepted_at' => $at,
        ]);
    }

    public function activate(CarbonInterface $at): void
    {
        if ($this->status !== ContractVersionStatus::Accepted) {
            throw new LogicException('Only an accepted ContractVersion may become active.');
        }

        if ($this->effective_from->isAfter($at)) {
            throw new LogicException('A ContractVersion cannot activate before its effective time.');
        }

        $this->saveLifecycle([
            'status' => ContractVersionStatus::Active,
            'activated_at' => $at,
        ]);
    }

    public function supersede(CarbonInterface $effectiveUntil, CarbonInterface $at): void
    {
        if ($this->status !== ContractVersionStatus::Active) {
            throw new LogicException('Only an active ContractVersion may be superseded.');
        }

        $this->saveLifecycle([
            'status' => ContractVersionStatus::Superseded,
            'effective_until' => $effectiveUntil,
            'superseded_at' => $at,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function saveLifecycle(array $attributes): void
    {
        $this->applyingLifecycle = true;

        try {
            $this->forceFill($attributes)->save();
        } finally {
            $this->applyingLifecycle = false;
        }
    }

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'status' => ContractVersionStatus::class,
            'effective_from' => 'datetime',
            'effective_until' => 'datetime',
            'proposed_at' => 'datetime',
            'accepted_at' => 'datetime',
            'activated_at' => 'datetime',
            'superseded_at' => 'datetime',
        ];
    }
}
