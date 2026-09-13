<?php

namespace App\Policies;

use App\Models\Actor;
use App\Models\GroupSpace;
use App\Models\SpaceContent;
use App\Models\User;

class SpaceContentPolicy
{
    public function __construct(private readonly GroupSpacePolicy $spaces) {}

    public function view(User $user, SpaceContent $content): bool
    {
        $content->loadMissing('space');

        return $this->spaces->view($user, $content->space);
    }

    public function create(User $user, GroupSpace $space): bool
    {
        return $this->spaces->post($user, $space);
    }

    public function update(User $user, SpaceContent $content): bool
    {
        return $content->status === 'draft' && $this->canOwnOrManage($user, $content);
    }

    public function publish(User $user, SpaceContent $content): bool
    {
        return $content->status === 'draft' && $this->canOwnOrManage($user, $content);
    }

    public function archive(User $user, SpaceContent $content): bool
    {
        return $content->status !== 'archived' && $this->canOwnOrManage($user, $content);
    }

    public function revisions(User $user, SpaceContent $content): bool
    {
        return $this->view($user, $content);
    }

    private function canOwnOrManage(User $user, SpaceContent $content): bool
    {
        $content->loadMissing('space');

        if (! $this->spaces->view($user, $content->space)) {
            return false;
        }

        $current = User::query()->with('actor')->find($user->id);
        if (! $current instanceof User || ! $current->actor instanceof Actor) {
            return false;
        }

        return (int) $content->author_actor_id === (int) $current->actor->id
            || $this->spaces->manage($current, $content->space);
    }
}
