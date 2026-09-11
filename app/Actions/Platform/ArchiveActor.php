<?php

namespace App\Actions\Platform;

use App\Models\Actor;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ArchiveActor
{
    public function execute(Actor $actor, User $administrator, string $reason): Actor
    {
        Gate::forUser($administrator)->authorize('archive', $actor);

        return DB::transaction(function () use ($actor, $administrator, $reason): Actor {
            $lockedActor = Actor::query()->with('user')->lockForUpdate()->findOrFail($actor->id);

            Gate::forUser($administrator)->authorize('archive', $lockedActor);

            $lockedActor->status = 'archived';
            $lockedActor->archived_at = now();
            $lockedActor->archived_by_user_id = $administrator->id;
            $lockedActor->archive_reason = $reason;
            $lockedActor->save();

            return $lockedActor;
        }, attempts: 3);
    }
}
