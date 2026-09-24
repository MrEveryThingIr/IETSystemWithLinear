<?php

namespace App\Policies;

use App\Models\Actor;
use App\Models\Relationship;
use App\Models\RelationshipParticipant;
use App\Models\User;
use App\RelationshipParticipantStatus;
use App\RelationshipStatus;

class RelationshipPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->currentActor($user) instanceof Actor;
    }

    public function create(User $user): bool
    {
        return $this->currentActor($user) instanceof Actor;
    }

    public function view(User $user, Relationship $relationship): bool
    {
        return $this->participant($user, $relationship) instanceof RelationshipParticipant;
    }

    public function participate(User $user, Relationship $relationship): bool
    {
        $current = Relationship::query()->find($relationship->id);
        $participant = $this->participant($user, $relationship);

        return $current instanceof Relationship
            && $current->status === RelationshipStatus::Active
            && $participant instanceof RelationshipParticipant
            && $participant->status === RelationshipParticipantStatus::Active;
    }

    public function manage(User $user, Relationship $relationship): bool
    {
        $participant = $this->participant($user, $relationship);

        return $this->participate($user, $relationship)
            && $participant instanceof RelationshipParticipant
            && $participant->can_manage;
    }

    public function respond(User $user, Relationship $relationship): bool
    {
        $current = Relationship::query()->find($relationship->id);
        $participant = $this->participant($user, $relationship);

        return $current instanceof Relationship
            && $current->status === RelationshipStatus::Proposed
            && $participant instanceof RelationshipParticipant
            && $participant->status === RelationshipParticipantStatus::Invited;
    }

    public function cancel(User $user, Relationship $relationship): bool
    {
        $current = Relationship::query()->find($relationship->id);
        $participant = $this->participant($user, $relationship);

        return $current instanceof Relationship
            && $current->status === RelationshipStatus::Proposed
            && $participant instanceof RelationshipParticipant
            && $participant->status === RelationshipParticipantStatus::Active
            && $participant->can_manage;
    }

    public function end(User $user, Relationship $relationship): bool
    {
        return $this->manage($user, $relationship);
    }

    private function participant(User $user, Relationship $relationship): ?RelationshipParticipant
    {
        $actor = $this->currentActor($user);

        if (! $actor instanceof Actor) {
            return null;
        }

        return RelationshipParticipant::query()
            ->where('relationship_id', $relationship->id)
            ->where('actor_id', $actor->id)
            ->first();
    }

    private function currentActor(User $user): ?Actor
    {
        $current = User::query()->with('actor')->find($user->id);

        if (! $current instanceof User
            || $current->status !== 'active'
            || $current->email_verified_at === null
            || ! $current->actor instanceof Actor
            || $current->actor->status !== 'active') {
            return null;
        }

        return $current->actor;
    }
}
