<?php

namespace App\Policies;

use App\Models\Interest;
use App\Models\User;

/** Interests belong to one person. Others may see one only where its holder put it on their profile. */
class InterestPolicy
{
    public function view(?User $user, Interest $interest): bool
    {
        return $this->holds($user, $interest) || $interest->visibility === Interest::VISIBILITY_PROFILE;
    }

    public function update(User $user, Interest $interest): bool
    {
        return $this->holds($user, $interest);
    }

    public function delete(User $user, Interest $interest): bool
    {
        return $this->holds($user, $interest);
    }

    private function holds(?User $user, Interest $interest): bool
    {
        return $user instanceof User
            && $user->status === 'active'
            && $user->actor !== null
            && (int) $user->actor->id === (int) $interest->holder_actor_id;
    }
}
