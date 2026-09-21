<?php

namespace App\Policies;

use App\Models\ActorProfileIntent;
use App\Models\User;
use App\ProfileIntentStatus;
use App\ProfileItemVisibility;

class ActorProfileIntentPolicy
{
    public function __construct(private readonly ActorProfilePolicy $profiles) {}

    public function view(?User $user, ActorProfileIntent $intent): bool
    {
        $intent->loadMissing('profile.actor');

        if (! $this->profiles->view($user, $intent->profile)) {
            return false;
        }

        if ($this->owns($user, $intent)) {
            return true;
        }

        if ($intent->status !== ProfileIntentStatus::Active) {
            return false;
        }

        return match ($intent->visibility) {
            ProfileItemVisibility::Inherited,
            ProfileItemVisibility::Public => true,
            ProfileItemVisibility::Authenticated => $this->isActiveVerified($user),
            ProfileItemVisibility::Private => false,
        };
    }

    public function update(User $user, ActorProfileIntent $intent): bool
    {
        return $this->profiles->update($user, $intent->profile);
    }

    private function owns(?User $user, ActorProfileIntent $intent): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        $current = User::query()->with('actor')->find($user->id);

        return $current instanceof User
            && $current->actor !== null
            && (int) $current->actor->id === (int) $intent->profile->actor_id;
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
