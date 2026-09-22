<?php

namespace App\Policies;

use App\Models\Actor;
use App\Models\GroupSpace;
use App\Models\SpaceContent;
use App\Models\SpaceContentRevision;
use App\Models\User;

class SpaceContentPolicy
{
    public function __construct(
        private readonly ContextPolicy $contexts,
        private readonly GroupSpacePolicy $spaces,
    ) {}

    public function view(User $user, SpaceContent $content): bool
    {
        $content->loadMissing('context');

        if (! $this->contexts->view($user, $content->context)) {
            return false;
        }

        if ($content->status === 'published') {
            $revision = $content->activeRevisionRecord();
            if (! $revision instanceof SpaceContentRevision) {
                return false;
            }

            return $revision->hasVerifiableManifest() || $this->canReadPrivate($user, $content);
        }

        return $this->canReadPrivate($user, $content);
    }

    public function create(User $user, GroupSpace $space): bool
    {
        return $this->spaces->post($user, $space);
    }

    public function interact(User $user, SpaceContent $content): bool
    {
        $content->loadMissing('context');

        if ($content->status !== 'published'
            || ! $this->view($user, $content)
            || ! $this->contexts->interactContent($user, $content->context)) {
            return false;
        }

        return $content->activeRevisionRecord() instanceof SpaceContentRevision;
    }

    public function update(User $user, SpaceContent $content): bool
    {
        return $content->status !== 'archived' && $this->canMutate($user, $content);
    }

    public function publish(User $user, SpaceContent $content): bool
    {
        return $content->status !== 'archived'
            && $content->draftRevisionRecord() !== null
            && $this->canMutate($user, $content);
    }

    public function archive(User $user, SpaceContent $content): bool
    {
        return $content->status !== 'archived' && $this->canMutate($user, $content);
    }

    public function restore(User $user, SpaceContent $content): bool
    {
        return $content->status === 'archived' && $this->canMutate($user, $content);
    }

    public function revisions(User $user, SpaceContent $content): bool
    {
        return $this->canReadPrivate($user, $content);
    }

    private function canReadPrivate(User $user, SpaceContent $content): bool
    {
        $content->loadMissing('context');

        if (! $this->contexts->view($user, $content->context)) {
            return false;
        }

        $current = User::query()->with('actor')->find($user->id);

        if (! $current instanceof User || ! $current->actor instanceof Actor) {
            return false;
        }

        $isAuthor = (int) $content->author_actor_id === (int) $current->actor->id;

        return $isAuthor || $this->contexts->reviewContent($current, $content->context);
    }

    private function canMutate(User $user, SpaceContent $content): bool
    {
        $content->loadMissing('context');

        if (! $this->contexts->view($user, $content->context)) {
            return false;
        }

        $current = User::query()->with('actor')->find($user->id);

        if (! $current instanceof User || ! $current->actor instanceof Actor) {
            return false;
        }

        $isAuthor = (int) $content->author_actor_id === (int) $current->actor->id;

        return ($isAuthor && $this->contexts->createContent($current, $content->context))
            || $this->contexts->manageContent($current, $content->context);
    }
}
