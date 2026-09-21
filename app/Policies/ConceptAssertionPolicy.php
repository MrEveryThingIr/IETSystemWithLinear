<?php

namespace App\Policies;

use App\Models\Actor;
use App\Models\ConceptAssertion;
use App\Models\Group;
use App\Models\SpaceContent;
use App\Models\SpaceContentRevision;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ConceptAssertionPolicy
{
    public function __construct(
        private readonly GroupPolicy $groups,
        private readonly SpaceContentPolicy $contents,
    ) {}

    public function create(User $user, Model $subject): bool
    {
        return match (true) {
            $subject instanceof Actor => $this->canAssertActor($user, $subject),
            $subject instanceof Group => $this->groups->manageConcepts($user, $subject),
            $subject instanceof SpaceContent => $this->contents->update($user, $subject),
            $subject instanceof SpaceContentRevision => $this->canAssertRevision($user, $subject),
            default => false,
        };
    }

    public function delete(User $user, ConceptAssertion $assertion): bool
    {
        return $this->create($user, $assertion->subjectModel());
    }

    private function canAssertActor(User $user, Actor $actor): bool
    {
        $current = User::query()->with('actor')->find($user->id);

        return $current instanceof User
            && $current->status === 'active'
            && $current->email_verified_at !== null
            && $current->actor instanceof Actor
            && (int) $current->actor->id === (int) $actor->id;
    }

    private function canAssertRevision(User $user, SpaceContentRevision $revision): bool
    {
        $content = $revision->content()->first();

        return $content instanceof SpaceContent && $this->contents->update($user, $content);
    }
}
