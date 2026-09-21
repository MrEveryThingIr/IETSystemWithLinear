<?php

namespace App\Policies;

use App\ConceptVocabularyScope;
use App\Models\Actor;
use App\Models\ConceptVocabulary;
use App\Models\Group;
use App\Models\User;
use App\PlatformCapability;

class ConceptVocabularyPolicy
{
    public function __construct(private readonly GroupPolicy $groups) {}

    public function createPlatform(User $user): bool
    {
        return $this->hasActiveActor($user)
            && $user->hasPlatformCapability(PlatformCapability::ManageConcepts);
    }

    public function createForActor(User $user, Actor $actor): bool
    {
        $current = $this->currentUser($user);

        return $current instanceof User
            && $current->actor instanceof Actor
            && (int) $current->actor->id === (int) $actor->id;
    }

    public function createForGroup(User $user, Group $group): bool
    {
        return $this->hasActiveActor($user) && $this->groups->manageConcepts($user, $group);
    }

    public function manage(User $user, ConceptVocabulary $vocabulary): bool
    {
        return match ($vocabulary->scope_type) {
            ConceptVocabularyScope::Platform => $this->createPlatform($user),
            ConceptVocabularyScope::Actor => $this->manageActorScope($user, $vocabulary),
            ConceptVocabularyScope::Group => $this->manageGroupScope($user, $vocabulary),
        };
    }

    private function manageActorScope(User $user, ConceptVocabulary $vocabulary): bool
    {
        $actor = Actor::query()->find($vocabulary->scope_id);

        return $actor instanceof Actor && $this->createForActor($user, $actor);
    }

    private function manageGroupScope(User $user, ConceptVocabulary $vocabulary): bool
    {
        $group = Group::query()->find($vocabulary->scope_id);

        return $group instanceof Group && $this->createForGroup($user, $group);
    }

    private function hasActiveActor(User $user): bool
    {
        return $this->currentUser($user)?->actor instanceof Actor;
    }

    private function currentUser(User $user): ?User
    {
        $current = User::query()->with('actor')->find($user->id);

        if (! $current instanceof User || $current->status !== 'active' || $current->email_verified_at === null) {
            return null;
        }

        return $current;
    }
}
