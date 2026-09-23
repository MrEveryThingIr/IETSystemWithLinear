<?php

namespace App\Models;

use App\ProfileIntentArrangementKind;
use App\ProfileIntentExchangePreference;
use App\ProfileIntentKind;
use App\ProfileIntentSubjectKind;
use App\ProfileIntentScheduleKind;
use App\ProfileIntentStatus;
use App\ProfileItemVisibility;
use Database\Factories\ActorProfileIntentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'title',
    'description',
    'importance_percent',
    'subject_kind',
    'arrangement_kind',
    'exchange_preference',
    'cash_min',
    'cash_max',
    'currency_code',
    'cash_basis',
    'exchange_notes',
    'quantity',
    'unit',
    'location_text',
    'origin_text',
    'destination_text',
    'round_trip',
    'return_after_days',
    'schedule_kind',
    'starts_on',
    'ends_on',
    'timezone',
    'recurrence_interval',
    'recurrence_weekdays',
    'recurrence_day_of_month',
    'time_window_start',
    'time_window_end',
    'visibility',
    'metadata',
])]
class ActorProfileIntent extends Model
{
    /** @use HasFactory<ActorProfileIntentFactory> */
    use HasFactory;

    protected $attributes = [
        'round_trip' => false,
        'schedule_kind' => ProfileIntentScheduleKind::Once->value,
        'recurrence_interval' => 1,
        'visibility' => ProfileItemVisibility::Inherited->value,
        'status' => ProfileIntentStatus::Active->value,
        'subject_kind' => ProfileIntentSubjectKind::Other->value,
        'arrangement_kind' => ProfileIntentArrangementKind::Other->value,
        'exchange_preference' => ProfileIntentExchangePreference::DiscussLater->value,
    ];

    private bool $applyingStatus = false;

    protected static function booted(): void
    {
        static::creating(function (self $intent): void {
            $intent->uuid ??= (string) Str::uuid();
        });

        static::updating(function (self $intent): void {
            if ($intent->isDirty([
                'uuid',
                'actor_profile_id',
                'concept_id',
                'kind',
                'created_by_actor_id',
            ])) {
                throw new LogicException('Profile intent semantic identity and provenance cannot be reassigned.');
            }

            if ($intent->isDirty(['status', 'closed_at']) && ! $intent->applyingStatus) {
                throw new LogicException('Profile intent lifecycle changes require the dedicated Action.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Profile intents are preserved; close them instead.');
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<ActorProfile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(ActorProfile::class, 'actor_profile_id');
    }

    /** @return BelongsTo<Concept, $this> */
    public function concept(): BelongsTo
    {
        return $this->belongsTo(Concept::class);
    }

    /** @return BelongsTo<Actor, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'created_by_actor_id');
    }

    public function applyStatus(ProfileIntentStatus $status): void
    {
        $this->applyingStatus = true;

        try {
            $this->forceFill([
                'status' => $status,
                'closed_at' => $status === ProfileIntentStatus::Closed ? now() : null,
            ])->save();
        } finally {
            $this->applyingStatus = false;
        }
    }

    protected function casts(): array
    {
        return [
            'kind' => ProfileIntentKind::class,
            'subject_kind' => ProfileIntentSubjectKind::class,
            'arrangement_kind' => ProfileIntentArrangementKind::class,
            'exchange_preference' => ProfileIntentExchangePreference::class,
            'schedule_kind' => ProfileIntentScheduleKind::class,
            'visibility' => ProfileItemVisibility::class,
            'status' => ProfileIntentStatus::class,
            'quantity' => 'decimal:4',
            'cash_min' => 'decimal:2',
            'cash_max' => 'decimal:2',
            'importance_percent' => 'integer',
            'round_trip' => 'boolean',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'recurrence_interval' => 'integer',
            'recurrence_weekdays' => 'array',
            'recurrence_day_of_month' => 'integer',
            'return_after_days' => 'integer',
            'closed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
