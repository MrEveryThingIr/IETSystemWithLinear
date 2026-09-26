<?php

namespace App\Models;

use App\PlanOccurrenceStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'uuid',
    'plan_id',
    'schedule_rule_id',
    'local_date',
    'scheduled_start_at',
    'scheduled_end_at',
    'window_start_at',
    'window_end_at',
    'timezone',
    'status',
    'actual_start_at',
    'actual_end_at',
    'completed_at',
    'origin_type',
    'origin_uuid',
    'metadata',
])]
class PlanOccurrence extends Model
{
    use HasFactory;

    private bool $applyingLifecycle = false;

    protected $attributes = [
        'status' => PlanOccurrenceStatus::Scheduled->value,
    ];

    protected static function booted(): void
    {
        static::creating(function (self $occurrence): void {
            $occurrence->uuid ??= (string) Str::uuid();
        });

        static::updating(function (self $occurrence): void {
            if ($occurrence->isDirty([
                'uuid',
                'plan_id',
                'schedule_rule_id',
                'local_date',
                'scheduled_start_at',
                'scheduled_end_at',
                'window_start_at',
                'window_end_at',
                'timezone',
                'origin_type',
                'origin_uuid',
                'metadata',
            ])) {
                throw new LogicException('Occurrence schedule and provenance are immutable.');
            }

            if ($occurrence->isDirty([
                'status',
                'actual_start_at',
                'actual_end_at',
                'completed_at',
            ]) && ! $occurrence->applyingLifecycle) {
                throw new LogicException('Occurrence lifecycle changes require a dedicated Action.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Occurrences preserve scheduling/execution history and cannot be deleted.');
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function transition(
        PlanOccurrenceStatus $status,
        ?CarbonInterface $actualStartAt = null,
        ?CarbonInterface $actualEndAt = null,
        ?CarbonInterface $completedAt = null,
    ): void {
        $current = $this->status;

        $allowed = match ($current) {
            PlanOccurrenceStatus::Scheduled => [
                PlanOccurrenceStatus::InProgress,
                PlanOccurrenceStatus::Skipped,
                PlanOccurrenceStatus::Cancelled,
            ],
            PlanOccurrenceStatus::InProgress => [
                PlanOccurrenceStatus::Completed,
                PlanOccurrenceStatus::Cancelled,
            ],
            PlanOccurrenceStatus::Completed,
            PlanOccurrenceStatus::Skipped,
            PlanOccurrenceStatus::Cancelled => [],
        };

        if (! in_array($status, $allowed, true)) {
            throw new LogicException("Occurrence cannot transition from {$current->value} to {$status->value}.");
        }

        $attributes = ['status' => $status];

        if ($actualStartAt !== null) {
            $attributes['actual_start_at'] = $actualStartAt;
        }

        if ($actualEndAt !== null) {
            $attributes['actual_end_at'] = $actualEndAt;
        }

        if ($completedAt !== null) {
            $attributes['completed_at'] = $completedAt;
        }

        $this->applyingLifecycle = true;

        try {
            $this->forceFill($attributes)->save();
        } finally {
            $this->applyingLifecycle = false;
        }
    }

    public function canStartAt(CarbonInterface $at): bool
    {
        return $this->status === PlanOccurrenceStatus::Scheduled
            && ! $at->lt($this->window_start_at)
            && ! $at->gt($this->window_end_at);
    }

    public function startWindowIsFutureAt(CarbonInterface $at): bool
    {
        return $this->status === PlanOccurrenceStatus::Scheduled
            && $at->lt($this->window_start_at);
    }

    public function startWindowHasPassedAt(CarbonInterface $at): bool
    {
        return $this->status === PlanOccurrenceStatus::Scheduled
            && $at->gt($this->window_end_at);
    }

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /** @return BelongsTo<PlanScheduleRule, $this> */
    public function scheduleRule(): BelongsTo
    {
        return $this->belongsTo(PlanScheduleRule::class, 'schedule_rule_id');
    }

    /** @return HasMany<PlanOccurrenceEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(PlanOccurrenceEvent::class)->orderBy('id');
    }

    /** @return BelongsToMany<Asset, $this> */
    public function assets(): BelongsToMany
    {
        return $this->belongsToMany(Asset::class, 'plan_occurrence_assets')
            ->withPivot(['uuid', 'added_by_actor_id'])
            ->withTimestamps();
    }

    /** @return HasMany<Fulfillment, $this> */
    public function fulfillments(): HasMany
    {
        return $this->hasMany(Fulfillment::class);
    }

    /** @return BelongsToMany<ContentEvidenceReference, $this> */
    public function evidenceReferences(): BelongsToMany
    {
        return $this->belongsToMany(
            ContentEvidenceReference::class,
            'plan_occurrence_evidence_references',
        )
            ->withPivot(['uuid', 'added_by_actor_id'])
            ->withTimestamps();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => PlanOccurrenceStatus::class,
            'local_date' => 'immutable_date',
            'scheduled_start_at' => 'immutable_datetime',
            'scheduled_end_at' => 'immutable_datetime',
            'window_start_at' => 'immutable_datetime',
            'window_end_at' => 'immutable_datetime',
            'actual_start_at' => 'immutable_datetime',
            'actual_end_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }
}
