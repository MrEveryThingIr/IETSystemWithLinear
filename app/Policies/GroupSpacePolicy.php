<?php

namespace App\Policies;

use App\Models\Actor;
use App\Models\Group;
use App\Models\GroupMembership;
use App\Models\GroupSpace;
use App\Models\GroupSpaceParticipant;
use App\Models\User;

class GroupSpacePolicy
{
    public function __construct(private readonly GroupPolicy $groups) {}

    public function view(User $user, GroupSpace $space): bool
    {
        $current = $this->currentWebsiteUser($user);

        if (! $current instanceof User || ! $this->spaceAndGroupAreActive($space)) {
            return false;
        }

        /** @var Actor $actor */
        $actor = $current->actor;
        $rule = $this->rule($space, $actor);

        if ($rule?->access === 'deny') {
            return false;
        }

        if ($space->access_mode === 'group') {
            return $this->groups->view($current, $space->group);
        }

        if ($space->access_mode !== 'restricted' || $rule?->access !== 'allow') {
            return false;
        }

        $membership = $this->membership($space, $actor);

        if (! $membership instanceof GroupMembership) {
            return true;
        }

        if ($membership->status !== 'active') {
            return false;
        }

        return $this->groups->view($current, $space->group);
    }

    public function post(User $user, GroupSpace $space): bool
    {
        return $this->view($user, $space);
    }

    public function manage(User $user, GroupSpace $space): bool
    {
        $current = $this->currentWebsiteUser($user);

        if (! $current instanceof User || ! $this->spaceAndGroupAreActive($space)) {
            return false;
        }

        if ($this->groups->manageSpaces($current, $space->group)) {
            return true;
        }

        /** @var Actor $actor */
        $actor = $current->actor;
        $rule = $this->rule($space, $actor);

        return $rule?->access === 'allow'
            && $rule->role === 'manager'
            && $this->view($current, $space);
    }

    public function manageParticipants(User $user, GroupSpace $space): bool
    {
        return $this->manage($user, $space);
    }

    private function currentWebsiteUser(User $user): ?User
    {
        $current = User::query()->with('actor')->find($user->id);

        if (
            ! $current instanceof User
            || $current->status !== 'active'
            || $current->email_verified_at === null
            || ! $current->actor instanceof Actor
            || $current->actor->status !== 'active'
        ) {
            return null;
        }

        return $current;
    }

    private function spaceAndGroupAreActive(GroupSpace $space): bool
    {
        if ($space->status !== 'active') {
            return false;
        }

        $space->loadMissing('group');

        if (! $space->group instanceof Group) {
            return false;
        }

        $groupStatus = $space->group->getAttribute('status');

        return $groupStatus === null || $groupStatus === 'active';
    }

    private function rule(GroupSpace $space, Actor $actor): ?GroupSpaceParticipant
    {
        return GroupSpaceParticipant::query()
            ->where('group_space_id', $space->id)
            ->where('actor_id', $actor->id)
            ->first();
    }

    private function membership(GroupSpace $space, Actor $actor): ?GroupMembership
    {
        return GroupMembership::query()
            ->where('group_id', $space->group_id)
            ->where('actor_id', $actor->id)
            ->first();
    }
}
