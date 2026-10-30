<?php

namespace App\Policies;

use App\ContextKind;
use App\Models\Actor;
use App\Models\ContentPlacement;
use App\Models\GroupSpace;
use App\Models\SpaceContent;
use App\Models\SpaceContentRevision;
use App\Models\User;
use App\Support\Content\ContactAudience;
use App\Support\RequestMemo;

class SpaceContentPolicy
{
    public function __construct(
        private readonly ContextPolicy $contexts,
        private readonly GroupSpacePolicy $spaces,
    ) {}

    public function view(User $user, SpaceContent $content): bool
    {
        $content->loadMissing('context');

        $sourceVisible = $this->contexts->view($user, $content->context);

        if ($content->status === 'published') {
            $revision = $content->activeRevisionRecord();
            if (! $revision instanceof SpaceContentRevision) {
                return false;
            }

            if ($revision->hasVerifiableManifest()) {
                return $sourceVisible || $this->canReadThroughPlacement($user, $content) || $this->readsAsContact($user, $content);
            }

            return ($sourceVisible && $this->canReadPrivate($user, $content)) || $this->readsAsContact($user, $content);
        }

        return $sourceVisible && $this->canReadPrivate($user, $content);
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

    /**
     * Permanent deletion is the author's own decision, in their own, a group
     * or a business place. Whether anyone else has engaged with it is
     * checked by App\Actions\Content\DeleteContentPermanently.
     */
    public function delete(User $user, SpaceContent $content): bool
    {
        $content->loadMissing('context');

        return in_array($content->context->kind, [ContextKind::Personal, ContextKind::GroupSpace, ContextKind::Business], true)
            && $this->isAuthor($user, $content)
            && $this->contexts->view($user, $content->context);
    }

    public function restore(User $user, SpaceContent $content): bool
    {
        return $content->status === 'archived' && $this->canMutate($user, $content);
    }

    public function revisions(User $user, SpaceContent $content): bool
    {
        return $this->canReadPrivate($user, $content);
    }

    /**
     * Open published Content to guests (public page or invitation links).
     *
     * Only the author decides. In a personal place that is enough; in a group
     * or business the author must also be someone who manages that place's
     * Content, because the place's members did not agree to a public audience.
     * Admissions, negotiations, contracts and system places are never opened.
     */
    public function share(User $user, SpaceContent $content): bool
    {
        if ($content->status !== 'published' || ! $content->activeRevisionRecord() instanceof SpaceContentRevision) {
            return false;
        }

        $content->loadMissing('context');
        if (! in_array($content->context->kind, [ContextKind::Personal, ContextKind::GroupSpace, ContextKind::Business], true)) {
            return false;
        }

        if (! $this->isAuthor($user, $content) || ! $this->contexts->view($user, $content->context)) {
            return false;
        }

        return $content->context->kind === ContextKind::Personal
            ? $this->contexts->createContent($user, $content->context)
            : $this->contexts->manageContent($user, $content->context);
    }

    /** See who was invited, revoke links and close public access (author or place managers). */
    public function manageSharing(User $user, SpaceContent $content): bool
    {
        $content->loadMissing('context');

        if (! $this->contexts->view($user, $content->context)) {
            return false;
        }

        return $this->isAuthor($user, $content) || $this->contexts->manageContent($user, $content->context);
    }

    /** Contacts-only Content of a personal place: its published edition is readable by the author's accepted contacts. */
    private function readsAsContact(User $user, SpaceContent $content): bool
    {
        return $content->context?->kind === ContextKind::Personal && ContactAudience::mayRead($user, $content);
    }

    private function isAuthor(User $user, SpaceContent $content): bool
    {
        $current = RequestMemo::user($user);

        return $current instanceof User
            && $current->actor instanceof Actor
            && (int) $content->author_actor_id === (int) $current->actor->id;
    }

    private function canReadThroughPlacement(User $user, SpaceContent $content): bool
    {
        return $content->placements()
            ->where('status', ContentPlacement::STATUS_ACTIVE)
            ->with('context')
            ->get()
            ->contains(fn (ContentPlacement $placement): bool => $this->contexts->view($user, $placement->context));
    }

    private function canReadPrivate(User $user, SpaceContent $content): bool
    {
        $content->loadMissing('context');

        if (! $this->contexts->view($user, $content->context)) {
            return false;
        }

        $current = RequestMemo::user($user);

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

        $current = RequestMemo::user($user);

        if (! $current instanceof User || ! $current->actor instanceof Actor) {
            return false;
        }

        $isAuthor = (int) $content->author_actor_id === (int) $current->actor->id;

        return ($isAuthor && $this->contexts->createContent($current, $content->context))
            || $this->contexts->manageContent($current, $content->context);
    }
}
