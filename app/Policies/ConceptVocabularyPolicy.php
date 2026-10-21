<?php

namespace App\Policies;

use App\ConceptVocabularyScope;
use App\Models\Actor;
use App\Models\Business;
use App\Models\ConceptVocabulary;
use App\Models\Group;
use App\Models\User;
use App\PlatformCapability;
use App\Support\BusinessAccess;
use App\Support\RequestMemo;

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
            ConceptVocabularyScope::Business => $this->manageBusinessScope($user, $vocabulary),
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

    public function createForBusiness(User $user, Business $business): bool
    {
        $current = $this->currentUser($user);

        return $current instanceof User && $current->actor instanceof Actor && BusinessAccess::canManage($current, $business);
    }

    private function manageBusinessScope(User $user, ConceptVocabulary $vocabulary): bool
    {
        $business = Business::query()->find($vocabulary->scope_id);

        return $business instanceof Business && $this->createForBusiness($user, $business);
    }

    private function hasActiveActor(User $user): bool
    {
        return $this->currentUser($user)?->actor instanceof Actor;
    }

    private function currentUser(User $user): ?User
    {
        $current = RequestMemo::user($user);

        if (! $current instanceof User || $current->status !== 'active' || $current->email_verified_at === null) {
            return null;
        }

        return $current;
    }
}
