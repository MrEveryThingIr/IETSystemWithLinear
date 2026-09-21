<?php

namespace App\Policies;

use App\Models\ActorProfile;
use App\Models\User;
use App\ProfileVisibility;

class ActorProfilePolicy
{
    public function view(?User $user, ActorProfile $profile): bool
    {
        $profile->loadMissing('actor');

        if ($profile->actor->status !== 'active') {
            return false;
        }

        if ($this->owns($user, $profile)) {
            return true;
        }

        return match ($profile->visibility) {
            ProfileVisibility::Public => true,
            ProfileVisibility::Authenticated => $this->isActiveVerified($user),
            ProfileVisibility::Private => false,
        };
    }

    public function update(User $user, ActorProfile $profile): bool
    {
        return $this->isActiveVerified($user) && $this->owns($user, $profile);
    }

    private function owns(?User $user, ActorProfile $profile): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        $current = User::query()->with('actor')->find($user->id);

        return $current instanceof User
            && $current->actor !== null
            && (int) $current->actor->id === (int) $profile->actor_id;
    }

    private function isActiveVerified(?User $user): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        $current = User::query()->find($user->id);

        return $current instanceof User
            && $current->status === 'active'
            && $current->email_verified_at !== null;
    }
}
