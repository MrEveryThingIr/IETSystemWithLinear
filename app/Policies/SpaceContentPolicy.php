<?php

namespace App\Policies;

use App\Models\Actor;
use App\Models\GroupSpace;
use App\Models\SpaceContent;
use App\Models\SpaceContentRevision;
use App\Models\User;

class SpaceContentPolicy
{
    public function __construct(private readonly GroupSpacePolicy $spaces) {}

    public function view(User $user, SpaceContent $content): bool
    {
        $content->loadMissing('space');

        if (! $this->spaces->view($user, $content->space)) {
            return false;
        }

        if ($content->status === 'published') {
            $revision = $content->activeRevisionRecord();
            if (! $revision instanceof SpaceContentRevision) {
                return false;
            }

            return $revision->hasVerifiableManifest() || $this->canOwnOrManage($user, $content);
        }

        return $this->canOwnOrManage($user, $content);
    }

    public function create(User $user, GroupSpace $space): bool
    {
        return $this->spaces->post($user, $space);
    }

    public function interact(User $user, SpaceContent $content): bool
    {
        $content->loadMissing('space');

        if ($content->status !== 'published' || ! $this->spaces->post($user, $content->space)) {
            return false;
        }

        $revision = $content->activeRevisionRecord();

        return $revision instanceof SpaceContentRevision && $revision->hasVerifiableManifest();
    }

    public function update(User $user, SpaceContent $content): bool
    {
        return $content->status !== 'archived' && $this->canOwnOrManage($user, $content);
    }

    public function publish(User $user, SpaceContent $content): bool
    {
        return $content->status !== 'archived'
            && $content->draftRevisionRecord() !== null
            && $this->canOwnOrManage($user, $content);
    }

    public function archive(User $user, SpaceContent $content): bool
    {
        return $content->status !== 'archived' && $this->canOwnOrManage($user, $content);
    }

    public function restore(User $user, SpaceContent $content): bool
    {
        return $content->status === 'archived' && $this->canOwnOrManage($user, $content);
    }

    public function revisions(User $user, SpaceContent $content): bool
    {
        return $this->canOwnOrManage($user, $content);
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
