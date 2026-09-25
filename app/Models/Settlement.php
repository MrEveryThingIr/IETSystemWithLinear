<?php

namespace App\Models;

use App\SettlementStatus;
use Carbon\CarbonInterface;
use Database\Factories\SettlementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'uuid',
    'financial_obligation_id',
    'amount_minor',
    'paid_at',
    'method',
    'reference',
    'note',
    'proposed_by_actor_id',
    'status',
    'confirmed_by_actor_id',
    'confirmed_at',
    'rejected_by_actor_id',
    'rejection_note',
    'rejected_at',
])]
class Settlement extends Model
{
    /** @use HasFactory<SettlementFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => SettlementStatus::PendingConfirmation->value,
    ];

    private bool $applyingLifecycle = false;

    protected static function booted(): void
    {
        static::creating(function (self $settlement): void {
            $settlement->uuid ??= (string) Str::uuid();
        });

        static::updating(function (self $settlement): void {
            if (! $settlement->applyingLifecycle) {
                throw new LogicException('Settlement lifecycle changes require dedicated Actions.');
            }

            if ($settlement->isDirty([
                'uuid',
                'financial_obligation_id',
                'amount_minor',
                'paid_at',
                'method',
                'reference',
                'note',
                'proposed_by_actor_id',
            ])) {
                throw new LogicException('Settlement payment claim facts are immutable.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Settlements preserve payment history and cannot be deleted.');
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<FinancialObligation, $this> */
    public function obligation(): BelongsTo
    {
        return $this->belongsTo(FinancialObligation::class, 'financial_obligation_id');
    }

    /** @return BelongsTo<Actor, $this> */
    public function proposedBy(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'proposed_by_actor_id');
    }

    /** @return BelongsTo<Actor, $this> */
    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'confirmed_by_actor_id');
    }

    /** @return BelongsTo<Actor, $this> */
    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'rejected_by_actor_id');
    }

    public function confirm(Actor $actor, CarbonInterface $at): void
    {
        if ($this->status !== SettlementStatus::PendingConfirmation) {
            throw new LogicException('Only a pending Settlement may be confirmed.');
        }

        $this->saveLifecycle([
            'status' => SettlementStatus::Confirmed,
            'confirmed_by_actor_id' => $actor->id,
            'confirmed_at' => $at,
        ]);
    }

    public function reject(Actor $actor, string $note, CarbonInterface $at): void
    {
        if ($this->status !== SettlementStatus::PendingConfirmation) {
            throw new LogicException('Only a pending Settlement may be rejected.');
        }

        $this->saveLifecycle([
            'status' => SettlementStatus::Rejected,
            'rejected_by_actor_id' => $actor->id,
            'rejection_note' => $note,
            'rejected_at' => $at,
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
            'amount_minor' => 'integer',
            'paid_at' => 'immutable_datetime',
            'status' => SettlementStatus::class,
            'confirmed_at' => 'immutable_datetime',
            'rejected_at' => 'immutable_datetime',
        ];
    }
}
