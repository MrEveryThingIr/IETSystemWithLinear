<?php

namespace App\Models;

use App\FulfillmentStatus;
use Carbon\CarbonInterface;
use Database\Factories\FulfillmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'uuid',
    'commitment_id',
    'plan_occurrence_id',
    'corrects_fulfillment_id',
    'submitted_by_actor_id',
    'quantity',
    'unit',
    'actual_start_at',
    'actual_end_at',
    'duration_minutes',
    'notes',
    'status',
    'submitted_at',
    'reviewed_at',
])]
class Fulfillment extends Model
{
    /** @use HasFactory<FulfillmentFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => FulfillmentStatus::Submitted->value,
    ];

    private bool $applyingLifecycle = false;

    protected static function booted(): void
    {
        static::creating(function (self $fulfillment): void {
            $fulfillment->uuid ??= (string) Str::uuid();
        });

        static::updating(function (self $fulfillment): void {
            if (! $fulfillment->applyingLifecycle) {
                throw new LogicException('Fulfillment facts are immutable outside dedicated lifecycle Actions.');
            }

            if ($fulfillment->isDirty([
                'uuid',
                'commitment_id',
                'plan_occurrence_id',
                'corrects_fulfillment_id',
                'submitted_by_actor_id',
                'quantity',
                'unit',
                'actual_start_at',
                'actual_end_at',
                'duration_minutes',
                'notes',
                'submitted_at',
            ])) {
                throw new LogicException('Submitted Fulfillment facts and evidence provenance are immutable.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Fulfillments preserve performance evidence and cannot be deleted.');
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<Commitment, $this> */
    public function commitment(): BelongsTo
    {
        return $this->belongsTo(Commitment::class);
    }

    /** @return BelongsTo<PlanOccurrence, $this> */
    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(PlanOccurrence::class, 'plan_occurrence_id');
    }

    /** @return BelongsTo<Fulfillment, $this> */
    public function corrects(): BelongsTo
    {
        return $this->belongsTo(self::class, 'corrects_fulfillment_id');
    }

    /** @return HasOne<Fulfillment, $this> */
    public function correction(): HasOne
    {
        return $this->hasOne(self::class, 'corrects_fulfillment_id');
    }

    /** @return BelongsTo<Actor, $this> */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'submitted_by_actor_id');
    }

    /** @return BelongsToMany<Asset, $this> */
    public function assets(): BelongsToMany
    {
        return $this->belongsToMany(Asset::class, 'fulfillment_assets')
            ->withPivot(['uuid', 'added_by_actor_id'])
            ->withTimestamps();
    }

    /** @return BelongsToMany<ContentEvidenceReference, $this> */
    public function evidenceReferences(): BelongsToMany
    {
        return $this->belongsToMany(
            ContentEvidenceReference::class,
            'fulfillment_evidence_references',
        )
            ->withPivot(['uuid', 'added_by_actor_id'])
            ->withTimestamps();
    }

    /** @return HasOne<FinancialObligation, $this> */
    public function financialObligation(): HasOne
    {
        return $this->hasOne(FinancialObligation::class);
    }

    /** @return HasOne<FulfillmentReview, $this> */
    public function review(): HasOne
    {
        return $this->hasOne(FulfillmentReview::class);
    }

    /** @return HasOne<FulfillmentDispute, $this> */
    public function dispute(): HasOne
    {
        return $this->hasOne(FulfillmentDispute::class);
    }

    public function transition(FulfillmentStatus $status, ?CarbonInterface $reviewedAt = null): void
    {
        $current = $this->status;

        $allowed = match ($current) {
            FulfillmentStatus::Submitted => [
                FulfillmentStatus::Accepted,
                FulfillmentStatus::Rejected,
                FulfillmentStatus::ClarificationRequested,
            ],
            FulfillmentStatus::ClarificationRequested,
            FulfillmentStatus::Rejected => [
                FulfillmentStatus::Corrected,
                FulfillmentStatus::Disputed,
            ],
            FulfillmentStatus::Accepted => [FulfillmentStatus::Disputed],
            FulfillmentStatus::Disputed => [
                FulfillmentStatus::Accepted,
                FulfillmentStatus::Rejected,
            ],
            FulfillmentStatus::Corrected => [],
        };

        if (! in_array($status, $allowed, true)) {
            throw new LogicException("Fulfillment cannot transition from {$current->value} to {$status->value}.");
        }

        $this->applyingLifecycle = true;

        try {
            $attributes = ['status' => $status];

            if ($reviewedAt !== null) {
                $attributes['reviewed_at'] = $reviewedAt;
            }

            $this->forceFill($attributes)->save();
        } finally {
            $this->applyingLifecycle = false;
        }
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'status' => FulfillmentStatus::class,
            'actual_start_at' => 'immutable_datetime',
            'actual_end_at' => 'immutable_datetime',
            'submitted_at' => 'immutable_datetime',
            'reviewed_at' => 'immutable_datetime',
            'duration_minutes' => 'integer',
        ];
    }
}
