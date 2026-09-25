<?php

namespace App\Models;

use App\CommitmentKind;
use App\CommitmentStatus;
use Database\Factories\CommitmentFactory;
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
    'contract_version_id',
    'created_by_actor_id',
    'obligor_actor_id',
    'beneficiary_actor_id',
    'kind',
    'title',
    'description',
    'quantity',
    'unit',
    'due_start_at',
    'due_end_at',
    'status',
])]
class Commitment extends Model
{
    /** @use HasFactory<CommitmentFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => CommitmentStatus::Active->value,
    ];

    protected static function booted(): void
    {
        static::creating(function (self $commitment): void {
            $commitment->uuid ??= (string) Str::uuid();
        });

        static::updating(function (): never {
            throw new LogicException('Commitments are immutable operational obligations in Phase 16.');
        });

        static::deleting(function (): never {
            throw new LogicException('Commitments preserve authoritative obligation history and cannot be deleted.');
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<ContractVersion, $this> */
    public function contractVersion(): BelongsTo
    {
        return $this->belongsTo(ContractVersion::class);
    }

    /** @return BelongsTo<Actor, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'created_by_actor_id');
    }

    /** @return BelongsTo<Actor, $this> */
    public function obligor(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'obligor_actor_id');
    }

    /** @return BelongsTo<Actor, $this> */
    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'beneficiary_actor_id');
    }

    /** @return HasOne<CommitmentPlanBinding, $this> */
    public function planBinding(): HasOne
    {
        return $this->hasOne(CommitmentPlanBinding::class);
    }

    /** @return HasMany<Fulfillment, $this> */
    public function fulfillments(): HasMany
    {
        return $this->hasMany(Fulfillment::class)->orderBy('id');
    }

    /** @return HasMany<CommitmentEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(CommitmentEvent::class)->orderBy('id');
    }

    protected function casts(): array
    {
        return [
            'kind' => CommitmentKind::class,
            'status' => CommitmentStatus::class,
            'quantity' => 'decimal:4',
            'due_start_at' => 'immutable_datetime',
            'due_end_at' => 'immutable_datetime',
        ];
    }
}
