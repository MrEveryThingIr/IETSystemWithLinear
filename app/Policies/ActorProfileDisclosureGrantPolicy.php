<?php

namespace App\Policies;

use App\Models\ActorProfileDisclosureGrant;
use App\Models\User;
use App\Support\RequestMemo;

class ActorProfileDisclosureGrantPolicy
{
    public function __construct(private readonly ActorProfilePolicy $profiles) {}

    public function view(User $user, ActorProfileDisclosureGrant $grant): bool
    {
        if (! $grant->isActive()) {
            return false;
        }

        $current = RequestMemo::user($user);

        if (! $current instanceof User
            || $current->status !== 'active'
            || $current->email_verified_at === null
            || $current->actor === null
            || $current->actor->status !== 'active'
            || (int) $current->actor->id !== (int) $grant->grantee_actor_id) {
            return false;
        }

        $grant->loadMissing('profile.actor');

        return $grant->profile->actor->status === 'active';
    }

    public function manage(User $user, ActorProfileDisclosureGrant $grant): bool
    {
        $grant->loadMissing('profile');

        return $this->profiles->update($user, $grant->profile);
    }
}
