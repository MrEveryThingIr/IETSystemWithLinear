<?php

namespace App\Models\Planner;

use App\Models\Actor;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * "Let's find a time": the organizer offers time options to one or more
 * people (users or guests with a link). See App\Actions\Planner\Meetings.
 */
#[Fillable([
    'uuid', 'organizer_actor_id', 'title', 'description', 'location', 'duration_minutes', 'timezone',
    'status', 'final_slot_id', 'organizer_plan_id', 'label_uuid', 'finalized_at', 'cancelled_at',
])]
class MeetingRequest extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_AGREED = 'agreed';

    public const STATUS_CANCELLED = 'cancelled';

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'finalized_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $meeting): void {
            $meeting->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    /** @return BelongsTo<Actor, $this> */
    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'organizer_actor_id');
    }

    /** @return HasMany<MeetingInvitee, $this> */
    public function invitees(): HasMany
    {
        return $this->hasMany(MeetingInvitee::class)->orderBy('id');
    }

    /** @return HasMany<MeetingSlot, $this> */
    public function slots(): HasMany
    {
        return $this->hasMany(MeetingSlot::class)->orderBy('starts_at');
    }

    /** @return BelongsTo<MeetingSlot, $this> */
    public function finalSlot(): BelongsTo
    {
        return $this->belongsTo(MeetingSlot::class, 'final_slot_id');
    }

    /** @return BelongsTo<Plan, $this> */
    public function organizerPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'organizer_plan_id');
    }
}
