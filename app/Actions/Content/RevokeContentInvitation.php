<?php

namespace App\Actions\Content;

use App\Models\Actor;
use App\Models\ContentInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** Turns an invitation link off immediately. Kept for the record, never deleted. */
class RevokeContentInvitation
{
    public function execute(ContentInvitation $invitation, User $user): ContentInvitation
    {
        return DB::transaction(function () use ($invitation, $user): ContentInvitation {
            $current = ContentInvitation::query()->with('content')->lockForUpdate()->findOrFail($invitation->id);
            Gate::forUser($user)->authorize('manageSharing', $current->content);

            if ($current->revoked_at !== null) {
                return $current;
            }

            $actor = User::query()->with('actor')->findOrFail($user->id)->actor;
            abort_unless($actor instanceof Actor, 403);

            $current->forceFill(['revoked_at' => now(), 'revoked_by_actor_id' => $actor->id])->save();

            return $current->refresh();
        }, 3);
    }
}
