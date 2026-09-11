<?php

namespace App\Policies;

use App\Models\Actor;
use App\Models\User;
use App\PlatformCapability;

class ActorPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPlatformCapability(PlatformCapability::ManageActors);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Actor $actor): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Actor $actor): bool
    {
        return false;
    }

    public function archive(User $user, Actor $actor): bool
    {
        if (! $this->viewAny($user) || $actor->status !== 'active') {
            return false;
        }

        return $actor->user === null || $actor->user->status === 'closed';
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Actor $actor): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Actor $actor): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Actor $actor): bool
    {
        return false;
    }
}
