<?php

namespace App\Models\Planner;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** One time option of a meeting, offered by the organizer or suggested by an invitee. */
#[Fillable(['uuid', 'meeting_request_id', 'starts_at', 'ends_at', 'suggested_by_invitee_id', 'status'])]
class MeetingSlot extends Model
{
    public const STATUS_OFFERED = 'offered';

    public const STATUS_WITHDRAWN = 'withdrawn';

    protected function casts(): array
    {
        return ['starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $slot): void {
            $slot->uuid ??= (string) Str::uuid();
        });
    }

    /** @return BelongsTo<MeetingRequest, $this> */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(MeetingRequest::class, 'meeting_request_id');
    }

    /** @return BelongsTo<MeetingInvitee, $this> */
    public function suggestedBy(): BelongsTo
    {
        return $this->belongsTo(MeetingInvitee::class, 'suggested_by_invitee_id');
    }

    /** @return HasMany<MeetingVote, $this> */
    public function votes(): HasMany
    {
        return $this->hasMany(MeetingVote::class);
    }
}
