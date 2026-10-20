<?php

namespace App\Models\Planner;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An invitee's answer for one time option. */
#[Fillable(['meeting_slot_id', 'meeting_invitee_id', 'answer'])]
class MeetingVote extends Model
{
    public const ANSWERS = ['yes', 'maybe', 'no'];

    /** @return BelongsTo<MeetingSlot, $this> */
    public function slot(): BelongsTo
    {
        return $this->belongsTo(MeetingSlot::class, 'meeting_slot_id');
    }

    /** @return BelongsTo<MeetingInvitee, $this> */
    public function invitee(): BelongsTo
    {
        return $this->belongsTo(MeetingInvitee::class, 'meeting_invitee_id');
    }
}
