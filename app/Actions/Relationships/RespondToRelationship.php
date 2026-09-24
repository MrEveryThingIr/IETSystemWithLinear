<?php

namespace App\Actions\Relationships;

use App\Models\Actor;
use App\Models\Relationship;
use App\Models\RelationshipEvent;
use App\Models\RelationshipParticipant;
use App\Models\User;
use App\RelationshipEventType;
use App\RelationshipParticipantStatus;
use App\RelationshipStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RespondToRelationship
{
    public function execute(Relationship $relationship, User $user, bool $accept): Relationship
    {
        return DB::transaction(function () use ($relationship, $user, $accept): Relationship {
            $locked = Relationship::query()->lockForUpdate()->findOrFail($relationship->id);
            $current = $this->currentUser($user);

            Gate::forUser($current)->authorize('respond', $locked);

            $participant = RelationshipParticipant::query()
                ->where('relationship_id', $locked->id)
                ->where('actor_id', $current->actor->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($accept) {
                $participant->applyStatus(RelationshipParticipantStatus::Active);

                RelationshipEvent::query()->create([
                    'relationship_id' => $locked->id,
                    'actor_id' => $current->actor->id,
                    'event_type' => RelationshipEventType::ParticipantAccepted,
                    'payload' => ['participant_id' => $participant->id],
                ]);

                $hasPending = RelationshipParticipant::query()
                    ->where('relationship_id', $locked->id)
                    ->where('status', RelationshipParticipantStatus::Invited->value)
                    ->exists();

                if (! $hasPending) {
                    $locked->applyStatus(RelationshipStatus::Active);

                    RelationshipEvent::query()->create([
                        'relationship_id' => $locked->id,
                        'actor_id' => $current->actor->id,
                        'event_type' => RelationshipEventType::Activated,
                        'payload' => [],
                    ]);
                }
            } else {
                $participant->applyStatus(RelationshipParticipantStatus::Declined);
                $locked->applyStatus(RelationshipStatus::Cancelled);

                RelationshipEvent::query()->create([
                    'relationship_id' => $locked->id,
                    'actor_id' => $current->actor->id,
                    'event_type' => RelationshipEventType::ParticipantDeclined,
                    'payload' => ['participant_id' => $participant->id],
                ]);

                RelationshipEvent::query()->create([
                    'relationship_id' => $locked->id,
                    'actor_id' => $current->actor->id,
                    'event_type' => RelationshipEventType::Cancelled,
                    'payload' => ['reason' => 'participant_declined'],
                ]);
            }

            return $locked->fresh([
                'purposeConcept.labels',
                'originatingIntent',
                'participants.actor.user',
                'contextBinding.context',
                'events',
            ]);
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
