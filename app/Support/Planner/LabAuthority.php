<?php

namespace App\Support\Planner;

use App\Models\Actor;
use App\Models\User;

/** Personal lab authorization is deliberately narrower than a shared Plan's view permission. */
final class LabAuthority
{
    public static function actor(User $user): Actor
    {
        $current = User::query()->with('actor')->find($user->id);
        abort_unless($current instanceof User && $current->status === 'active'
            && $current->hasVerifiedEmail() && $current->actor?->status === 'active', 403);

        return $current->actor;
    }

    public static function owns(User $user, int $ownerActorId): Actor
    {
        $actor = self::actor($user);
        abort_unless((int) $actor->id === $ownerActorId, 403);

        return $actor;
    }
}
