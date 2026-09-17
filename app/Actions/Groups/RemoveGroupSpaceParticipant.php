<?php

namespace App\Actions\Groups;

use App\Models\Actor;
use App\Models\GroupSpace;
use App\Models\GroupSpaceParticipant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RemoveGroupSpaceParticipant
{
    public function execute(GroupSpace $space, Actor $target, User $user): bool
    {
        return DB::transaction(function () use ($space, $target, $user): bool {
            /** @var GroupSpace $lockedSpace */
            $lockedSpace = GroupSpace::query()->with('group')->lockForUpdate()->findOrFail($space->id);

            Gate::forUser($user)->authorize('manageParticipants', $lockedSpace);

            Actor::query()->lockForUpdate()->findOrFail($target->id);

            /** @var GroupSpaceParticipant|null $participant */
            $participant = GroupSpaceParticipant::query()
                ->where('group_space_id', $lockedSpace->id)
                ->where('actor_id', $target->id)
                ->lockForUpdate()
                ->first();

            if (! $participant instanceof GroupSpaceParticipant) {
                return false;
            }

            $participant->delete();

            return true;
        }, attempts: 3);
    }
}
