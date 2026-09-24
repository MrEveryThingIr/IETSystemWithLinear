<?php

namespace App\Models;

use App\PlanStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'uuid',
    'context_id',
    'created_by_actor_id',
    'title',
    'description',
    'timezone',
    'status',
    'origin_type',
    'origin_uuid',
    'metadata',
])]
class Plan extends Model
{
    use HasFactory;

    private bool $applyingLifecycle = false;

    protected $attributes = [
        'status' => PlanStatus::Active->value,
    ];

    protected static function booted(): void
    {
        static::creating(function (self $plan): void {
            $plan->uuid ??= (string) Str::uuid();
        });

        static::updating(function (self $plan): void {
            if ($plan->isDirty([
                'uuid',
                'context_id',
                'created_by_actor_id',
                'origin_type',
                'origin_uuid',
            ])) {
                throw new LogicException('Plan identity, Context, creator, and provenance are immutable.');
            }

            if ($plan->isDirty('status') && ! $plan->applyingLifecycle) {
                throw new LogicException('Plan lifecycle changes require a dedicated Action.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Plans preserve scheduling history and cannot be deleted directly.');
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function applyStatus(PlanStatus $status): void
    {
        $current = $this->status;

        $allowed = match ($current) {
            PlanStatus::Active => [PlanStatus::Paused, PlanStatus::Completed, PlanStatus::Cancelled],
            PlanStatus::Paused => [PlanStatus::Active, PlanStatus::Completed, PlanStatus::Cancelled],
            PlanStatus::Completed, PlanStatus::Cancelled => [],
        };

        if (! in_array($status, $allowed, true)) {
            throw new LogicException("Plan cannot transition from {$current->value} to {$status->value}.");
        }

        $this->applyingLifecycle = true;

        try {
            $this->forceFill(['status' => $status])->save();
        } finally {
            $this->applyingLifecycle = false;
        }
    }

    /** @return BelongsTo<Context, $this> */
    public function context(): BelongsTo
    {
        return $this->belongsTo(Context::class);
    }

    /** @return BelongsTo<Actor, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'created_by_actor_id');
    }

    /** @return HasMany<PlanParticipant, $this> */
    public function participants(): HasMany
    {
        return $this->hasMany(PlanParticipant::class);
    }

    /** @return HasMany<PlanScheduleRule, $this> */
    public function scheduleRules(): HasMany
    {
        return $this->hasMany(PlanScheduleRule::class);
    }

    /** @return HasMany<PlanOccurrence, $this> */
    public function occurrences(): HasMany
    {
        return $this->hasMany(PlanOccurrence::class);
    }

    /** @return HasMany<PlanReminder, $this> */
    public function reminders(): HasMany
    {
        return $this->hasMany(PlanReminder::class);
    }

    /** @return HasMany<PlanEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(PlanEvent::class)->orderBy('id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => PlanStatus::class,
            'metadata' => 'array',
        ];
    }
}
