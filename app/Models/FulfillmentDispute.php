<?php

namespace App\Models;

use App\FulfillmentDisputeStatus;
use App\FulfillmentStatus;
use Carbon\CarbonInterface;
use Database\Factories\FulfillmentDisputeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'uuid',
    'fulfillment_id',
    'opened_by_actor_id',
    'original_status',
    'reason',
    'status',
    'resolved_by_actor_id',
    'resolution_status',
    'resolution_note',
    'opened_at',
    'resolved_at',
])]
class FulfillmentDispute extends Model
{
    /** @use HasFactory<FulfillmentDisputeFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => FulfillmentDisputeStatus::Open->value,
    ];

    private bool $applyingLifecycle = false;

    protected static function booted(): void
    {
        static::creating(function (self $dispute): void {
            $dispute->uuid ??= (string) Str::uuid();
        });

        static::updating(function (self $dispute): void {
            if (! $dispute->applyingLifecycle) {
                throw new LogicException('Fulfillment dispute lifecycle changes require a dedicated Action.');
            }

            if ($dispute->isDirty([
                'uuid',
                'fulfillment_id',
                'opened_by_actor_id',
                'original_status',
                'reason',
                'opened_at',
            ])) {
                throw new LogicException('Fulfillment dispute provenance is immutable.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Fulfillment disputes preserve dispute history.');
        });
    }

    /** @return BelongsTo<Fulfillment, $this> */
    public function fulfillment(): BelongsTo
    {
        return $this->belongsTo(Fulfillment::class);
    }

    /** @return BelongsTo<Actor, $this> */
    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'opened_by_actor_id');
    }

    /** @return BelongsTo<Actor, $this> */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'resolved_by_actor_id');
    }

    public function resolve(
        Actor $actor,
        FulfillmentStatus $resolution,
        ?string $note,
        CarbonInterface $at,
    ): void {
        if ($this->status !== FulfillmentDisputeStatus::Open) {
            throw new LogicException('Only an open Fulfillment dispute may be resolved.');
        }

        if (! in_array($resolution, [FulfillmentStatus::Accepted, FulfillmentStatus::Rejected], true)) {
            throw new LogicException('Fulfillment dispute resolution must be accepted or rejected.');
        }

        $this->applyingLifecycle = true;

        try {
            $this->forceFill([
                'status' => FulfillmentDisputeStatus::Resolved,
                'resolved_by_actor_id' => $actor->id,
                'resolution_status' => $resolution,
                'resolution_note' => $note,
                'resolved_at' => $at,
            ])->save();
        } finally {
            $this->applyingLifecycle = false;
        }
    }

    protected function casts(): array
    {
        return [
            'status' => FulfillmentDisputeStatus::class,
            'original_status' => FulfillmentStatus::class,
            'resolution_status' => FulfillmentStatus::class,
            'opened_at' => 'immutable_datetime',
            'resolved_at' => 'immutable_datetime',
        ];
    }
}
