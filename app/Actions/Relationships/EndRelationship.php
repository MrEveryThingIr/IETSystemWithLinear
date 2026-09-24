<?php

namespace App\Actions\Relationships;

use App\Models\Actor;
use App\Models\Relationship;
use App\Models\RelationshipEvent;
use App\Models\User;
use App\RelationshipEventType;
use App\RelationshipStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class EndRelationship
{
    public function execute(Relationship $relationship, User $user): Relationship
    {
        return DB::transaction(function () use ($relationship, $user): Relationship {
            $locked = Relationship::query()->lockForUpdate()->findOrFail($relationship->id);
            $current = $this->currentUser($user);

            Gate::forUser($current)->authorize('end', $locked);

            $locked->applyStatus(RelationshipStatus::Ended);

            RelationshipEvent::query()->create([
                'relationship_id' => $locked->id,
                'actor_id' => $current->actor->id,
                'event_type' => RelationshipEventType::Ended,
                'payload' => [],
            ]);

            return $locked->fresh(['participants.actor.user', 'contextBinding.context', 'events']);
        }, attempts: 3);
    }

    private function currentUser(User $user): User
    {
        $current = User::query()->with('actor')->find($user->id);

        abort_unless(
            $current instanceof User
            && $current->status === 'active'
            && $current->email_verified_at !== null
            && $current->actor instanceof Actor
            && $current->actor->status === 'active',
            403,
        );

        return $current;
    }
}
