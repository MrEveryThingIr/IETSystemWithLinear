<?php

namespace App\Models;

use App\IetExchangeDirection;
use App\IetExchangeStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'uuid',
    'user_id',
    'direction',
    'fiat_unit_code',
    'fiat_amount_minor',
    'iet_valuation_snapshot_id',
    'iet_amount_minor',
    'status',
    'external_reference',
    'note',
    'reviewed_by_actor_id',
    'reviewed_at',
    'review_note',
])]
class IetExchangeRequest extends Model
{
    protected $attributes = [
        'status' => IetExchangeStatus::Pending->value,
        'fiat_unit_code' => 'USD',
    ];

    private bool $applyingLifecycle = false;

    protected static function booted(): void
    {
        static::creating(function (self $request): void {
            $request->uuid ??= (string) Str::uuid();
        });

        static::updating(function (self $request): void {
            if (! $request->applyingLifecycle) {
                throw new LogicException('IET Exchange lifecycle changes require dedicated Actions.');
            }

            if ($request->isDirty([
                'uuid',
                'user_id',
                'direction',
                'fiat_unit_code',
                'fiat_amount_minor',
                'iet_valuation_snapshot_id',
                'iet_amount_minor',
                'external_reference',
                'note',
            ])) {
                throw new LogicException('IET Exchange request quote and provenance are immutable.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('IET Exchange requests preserve financial history and cannot be deleted.');
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<IetValuationSnapshot, $this> */
    public function valuation(): BelongsTo
    {
        return $this->belongsTo(IetValuationSnapshot::class, 'iet_valuation_snapshot_id');
    }

    /** @return BelongsTo<Actor, $this> */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'reviewed_by_actor_id');
    }

    public function confirm(Actor $actor, CarbonInterface $at, ?string $note = null): void
    {
        $this->transition(IetExchangeStatus::Confirmed, $actor, $at, $note);
    }

    public function reject(Actor $actor, CarbonInterface $at, ?string $note = null): void
    {
        $this->transition(IetExchangeStatus::Rejected, $actor, $at, $note);
    }

    private function transition(
        IetExchangeStatus $status,
        Actor $actor,
        CarbonInterface $at,
        ?string $note,
    ): void {
        if ($this->status !== IetExchangeStatus::Pending) {
            throw new LogicException('Only a pending IET Exchange request may be reviewed.');
        }

        $this->applyingLifecycle = true;

        try {
            $this->forceFill([
                'status' => $status,
                'reviewed_by_actor_id' => $actor->id,
                'reviewed_at' => $at,
                'review_note' => filled($note) ? trim((string) $note) : null,
            ])->save();
        } finally {
            $this->applyingLifecycle = false;
        }
    }

    protected function casts(): array
    {
        return [
            'direction' => IetExchangeDirection::class,
            'status' => IetExchangeStatus::class,
            'fiat_amount_minor' => 'integer',
            'iet_amount_minor' => 'integer',
            'reviewed_at' => 'immutable_datetime',
        ];
    }
}
