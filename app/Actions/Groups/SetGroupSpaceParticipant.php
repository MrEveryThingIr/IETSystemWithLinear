<?php

namespace App\Actions\Groups;

use App\Models\Actor;
use App\Models\GroupSpace;
use App\Models\GroupSpaceParticipant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SetGroupSpaceParticipant
{
    public function execute(
        GroupSpace $space,
        Actor $target,
        User $user,
        string $access,
        string $role,
    ): GroupSpaceParticipant {
        abort_unless(in_array($access, ['allow', 'deny'], true), 422, 'Invalid Space participant access.');
        abort_unless(in_array($role, ['participant', 'manager'], true), 422, 'Invalid Space participant role.');
        abort_if($access === 'deny' && $role === 'manager', 422, 'A denied Actor cannot be a Space manager.');

        return DB::transaction(function () use ($space, $target, $user, $access, $role): GroupSpaceParticipant {
            /** @var GroupSpace $lockedSpace */
            $lockedSpace = GroupSpace::query()->with('group')->lockForUpdate()->findOrFail($space->id);

            Gate::forUser($user)->authorize('manageParticipants', $lockedSpace);

            /** @var Actor $lockedTarget */
            $lockedTarget = Actor::query()->lockForUpdate()->findOrFail($target->id);
            $grantor = $this->currentActor($user);

            /** @var GroupSpaceParticipant|null $participant */
            $participant = GroupSpaceParticipant::query()
                ->where('group_space_id', $lockedSpace->id)
                ->where('actor_id', $lockedTarget->id)
                ->lockForUpdate()
                ->first();

            if (
                $participant instanceof GroupSpaceParticipant
                && $participant->access === $access
                && $participant->role === $role
            ) {
                return $participant;
            }

            $participant ??= new GroupSpaceParticipant;
            $participant->group_space_id = $lockedSpace->id;
            $participant->actor_id = $lockedTarget->id;
            $participant->access = $access;
            $participant->role = $role;
            $participant->granted_by_actor_id = $grantor->id;
            $participant->save();

            return $participant->refresh();
        }, attempts: 3);
    }

    private function currentActor(User $user): Actor
    {
        $current = User::query()->with('actor')->findOrFail($user->id);

        abort_unless(
            $current->status === 'active'
            && $current->email_verified_at !== null
            && $current->actor instanceof Actor
            && $current->actor->status === 'active',
            403,
        );

        return $current->actor;
    }
}
