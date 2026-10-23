<?php

namespace App\Models;

use App\Models\Planner\OccurrenceParticipation;
use App\PlanOccurrenceStatus;
use App\PlanOccurrenceWindowState;
use App\PlanTimingMode;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
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
    'policy_snapshot',
])]
class PlanOccurrence extends Model
{
    use HasFactory;

    private bool $applyingLifecycle = false;

    private bool $applyingLiving = false;

    protected $attributes = [
        'status' => PlanOccurrenceStatus::Scheduled->value,
    ];

    protected static function booted(): void
    {
        static::creating(function (self $occurrence): void {
            $occurrence->uuid ??= (string) Str::uuid();
        });

        static::updating(function (self $occurrence): void {
            if ($occurrence->isDirty(['execution_phase', 'evaluated_outcome', 'evidence_confidence', 'window_closed_at', 'policy_snapshot', 'active_seconds']) && ! $occurrence->applyingLiving) {
                throw new LogicException('Living updates require their dedicated action.');
            }
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

    /** @param array<string, mixed> $attributes Internal projection update; target changes are never accepted. */
    public function applyLiving(array $attributes): void
    {
        $allowed = ['execution_phase', 'evaluated_outcome', 'evidence_confidence', 'window_closed_at', 'policy_snapshot', 'active_seconds', 'actual_start_at', 'actual_end_at'];
        if (array_diff(array_keys($attributes), $allowed) !== []) {
            throw new LogicException('Cannot alter targets through an execution projection.');
        }
        $this->applyingLiving = $this->applyingLifecycle = true;
        try {
            $this->forceFill($attributes)->save();
        } finally {
            $this->applyingLiving = $this->applyingLifecycle = false;
        }
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

    public function windowState(?CarbonInterface $at = null): PlanOccurrenceWindowState
    {
        return match ($this->status) {
            PlanOccurrenceStatus::InProgress => PlanOccurrenceWindowState::InProgress,
            PlanOccurrenceStatus::Completed => PlanOccurrenceWindowState::Completed,
            PlanOccurrenceStatus::Skipped => PlanOccurrenceWindowState::Skipped,
            PlanOccurrenceStatus::Cancelled => PlanOccurrenceWindowState::Cancelled,
            PlanOccurrenceStatus::Scheduled => $this->scheduledWindowState($at),
        };
    }

    public function executionPhase(?CarbonInterface $at = null): string
    {
        if ($this->evaluated_outcome !== null && $this->evaluated_outcome !== 'unconfirmed') {
            return $this->evaluated_outcome;
        }
        if (in_array($this->execution_phase, ['running', 'paused'], true)) {
            return $this->execution_phase === 'running' ? 'in_progress' : 'paused';
        }

        return $this->windowState($at)->value;
    }

    public function canStart(?CarbonInterface $at = null): bool
    {
        if ((int) data_get($this->policy_snapshot, 'version', 0) > 0) {
            $moment = $at === null ? CarbonImmutable::now('UTC') : CarbonImmutable::instance($at);
            $style = data_get($this->policy_snapshot, 'window_style', 'hard');
            $earliest = $style === 'deadline' ? $this->created_at : $this->window_start_at;
            $latest = $style === 'hard' ? $this->window_end_at : $this->window_end_at->addDays((int) data_get($this->policy_snapshot, 'reporting_days', 30));

            return ! in_array($this->status, [PlanOccurrenceStatus::Cancelled, PlanOccurrenceStatus::Skipped, PlanOccurrenceStatus::Completed], true)
                && $moment->betweenIncluded($earliest, $latest) && $this->prerequisitesSatisfied();
        }

        return in_array($this->windowState($at), [
            PlanOccurrenceWindowState::Ready,
            PlanOccurrenceWindowState::Late,
        ], true);
    }

    public function canStartAt(CarbonInterface $at): bool
    {
        return $this->canStart($at);
    }

    private function scheduledWindowState(?CarbonInterface $at = null): PlanOccurrenceWindowState
    {
        $moment = $at instanceof CarbonInterface
            ? CarbonImmutable::parse($at->toIso8601String())->utc()
            : CarbonImmutable::now('UTC');

        if ($moment->lt($this->window_start_at->utc())) {
            return PlanOccurrenceWindowState::Upcoming;
        }

        $readyThrough = $this->scheduleRule?->timing_mode === PlanTimingMode::FlexibleDay
            ? $this->scheduled_end_at->utc()
            : $this->scheduled_start_at->utc();

        if ($moment->lte($readyThrough)) {
            return PlanOccurrenceWindowState::Ready;
        }

        if ($moment->lte($this->window_end_at->utc())) {
            return PlanOccurrenceWindowState::Late;
        }

        return PlanOccurrenceWindowState::Unconfirmed;
    }

    public function prerequisitesSatisfied(): bool
    {
        return $this->remainingRequiredPrerequisites() === 0;
    }

    public function remainingRequiredPrerequisites(): int
    {
        $requiredIds = $this->requiredPrerequisites()->pluck('id');

        if ($requiredIds->isEmpty()) {
            return 0;
        }

        $completedIds = $this->completedPrerequisiteChecks()
            ->pluck('plan_prerequisite_id');

        return $requiredIds->diff($completedIds)->count();
    }

    /** @return Collection<int, PlanPrerequisite> */
    private function requiredPrerequisites(): Collection
    {
        $plan = $this->plan;

        $items = $plan->relationLoaded('prerequisites')
            ? $plan->prerequisites
            : $plan->prerequisites()->get();

        return $items->where('is_required', true)->values();
    }

    /** @return Collection<int, PlanOccurrencePrerequisiteCheck> */
    private function completedPrerequisiteChecks(): Collection
    {
        $checks = $this->relationLoaded('prerequisiteChecks')
            ? $this->prerequisiteChecks
            : $this->prerequisiteChecks()->get();

        return $checks->filter(fn (PlanOccurrencePrerequisiteCheck $check): bool => $check->completed_at !== null)
            ->values();
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

    /** @return HasMany<OccurrenceParticipation, $this> */
    public function participations(): HasMany
    {
        return $this->hasMany(OccurrenceParticipation::class);
    }

    /** @return HasMany<PlanOccurrencePrerequisiteCheck, $this> */
    public function prerequisiteChecks(): HasMany
    {
        return $this->hasMany(PlanOccurrencePrerequisiteCheck::class);
    }

    /** @return HasMany<PlanOccurrenceExpense, $this> */
    public function expenses(): HasMany
    {
        return $this->hasMany(PlanOccurrenceExpense::class)->orderBy('occurred_at')->orderBy('id');
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
            'policy_snapshot' => 'array',
            'window_closed_at' => 'immutable_datetime',
            'active_seconds' => 'integer',
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
