<?php

namespace App\Models;

use App\PlanScheduleFrequency;
use App\PlanScheduleRuleStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'uuid',
    'plan_id',
    'created_by_actor_id',
    'frequency',
    'interval',
    'starts_on',
    'start_time',
    'duration_minutes',
    'weekdays',
    'selected_dates',
    'ends_on',
    'occurrence_limit',
    'window_before_minutes',
    'window_after_minutes',
    'timezone',
    'status',
])]
class PlanScheduleRule extends Model
{
    use HasFactory;

    private bool $applyingLifecycle = false;

    protected $attributes = [
        'interval' => 1,
        'duration_minutes' => 60,
        'window_before_minutes' => 0,
        'window_after_minutes' => 0,
        'status' => PlanScheduleRuleStatus::Active->value,
    ];

    protected static function booted(): void
    {
        static::creating(function (self $rule): void {
            $rule->uuid ??= (string) Str::uuid();
        });

        static::updating(function (self $rule): void {
            if ($rule->isDirty([
                'uuid',
                'plan_id',
                'created_by_actor_id',
                'frequency',
                'interval',
                'starts_on',
                'start_time',
                'duration_minutes',
                'weekdays',
                'selected_dates',
                'ends_on',
                'occurrence_limit',
                'window_before_minutes',
                'window_after_minutes',
                'timezone',
            ])) {
                throw new LogicException('Schedule rules are immutable; cancel and replace the rule instead.');
            }

            if ($rule->isDirty('status') && ! $rule->applyingLifecycle) {
                throw new LogicException('Schedule rule lifecycle changes require a dedicated Action.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Schedule rules preserve occurrence provenance and cannot be deleted.');
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function cancel(): void
    {
        if ($this->status !== PlanScheduleRuleStatus::Active) {
            throw new LogicException('Only an active Schedule Rule may be cancelled.');
        }

        $this->applyingLifecycle = true;

        try {
            $this->forceFill(['status' => PlanScheduleRuleStatus::Cancelled])->save();
        } finally {
            $this->applyingLifecycle = false;
        }
    }

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /** @return BelongsTo<Actor, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'created_by_actor_id');
    }

    /** @return HasMany<PlanOccurrence, $this> */
    public function occurrences(): HasMany
    {
        return $this->hasMany(PlanOccurrence::class, 'schedule_rule_id');
    }

    /** @return HasMany<PlanReminder, $this> */
    public function reminders(): HasMany
    {
        return $this->hasMany(PlanReminder::class, 'schedule_rule_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'frequency' => PlanScheduleFrequency::class,
            'status' => PlanScheduleRuleStatus::class,
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'weekdays' => 'array',
            'selected_dates' => 'array',
            'interval' => 'integer',
            'duration_minutes' => 'integer',
            'occurrence_limit' => 'integer',
            'window_before_minutes' => 'integer',
            'window_after_minutes' => 'integer',
        ];
    }
}
