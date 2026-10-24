<?php

namespace App\Actions\Market;

use App\Models\ActorProfileIntent;
use App\Models\IntentVersion;
use App\Models\User;
use App\ProfileIntentStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** Copies an old version into the pending working copy; history itself is never rewritten. */
final class RestoreIntentVersion
{
    public function execute(User $user, ActorProfileIntent $intent, IntentVersion $version): ActorProfileIntent
    {
        Gate::forUser($user)->authorize('update', $intent);
        abort_unless($version->actor_profile_intent_id === $intent->id, 404);

        return DB::transaction(function () use ($user, $intent, $version): ActorProfileIntent {
            $locked = ActorProfileIntent::query()->lockForUpdate()->findOrFail($intent->id);
            Gate::forUser($user)->authorize('update', $locked);
            abort_if($locked->status === ProfileIntentStatus::Closed, 409);
            $snapshot = $version->snapshot;
            // Publication applies this exactly like composer edits; unchanged content publishes as a no-op.
            $locked->forceFill(['working_copy' => ['core' => $snapshot['core'], 'details' => $snapshot['details']]])->save();

            return $locked->fresh();
        }, 3);
    }
}
