<?php

namespace App\Actions\Groups;

use App\Models\Actor;
use App\Models\Admission;
use App\Models\GroupInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RedeemGroupInvitation
{
    public function execute(string $token, Actor $actor, string $email): Admission
    {
        return DB::transaction(function () use ($token, $actor, $email): Admission {
            $invitation = GroupInvitation::query()->where('token', $token)->lockForUpdate()->firstOrFail();
            if ($invitation->email !== null && $invitation->email !== $email) {
                throw ValidationException::withMessages(['invitation' => 'This invitation is reserved for a different email address.']);
            }
            $acceptance = $invitation->acceptances()->where('accepted_by_actor_id', $actor->id)->first();
            if ($acceptance === null) {
                abort_if($invitation->revoked_at !== null || ($invitation->expires_at !== null && $invitation->expires_at->isPast()) || ($invitation->max_uses !== null && $invitation->uses_count >= $invitation->max_uses), 404);
                $invitation->acceptances()->create(['accepted_by_actor_id' => $actor->id, 'accepted_at' => now()]);
                $invitation->increment('uses_count');
            }
            $admission = Admission::query()->where('group_id', $invitation->group_id)->where('candidate_actor_id', $actor->id)->lockForUpdate()->first();
            if ($admission === null) {
                $admission = Admission::create(['group_id' => $invitation->group_id, 'candidate_actor_id' => $actor->id, 'source_invitation_id' => $invitation->id, 'status' => 'draft']);
                $admission->events()->create(['actor_id' => $actor->id, 'event' => 'admission.created_from_invitation']);
            } elseif (in_array($admission->status, ['rejected', 'cancelled'], true)) {
                $admission->transitionTo('draft', $actor, 'Resumed through invitation redemption.');
            }
            return $admission;
        });
    }
}
