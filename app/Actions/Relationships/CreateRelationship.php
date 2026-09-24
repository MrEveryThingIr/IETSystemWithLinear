<?php

namespace App\Actions\Relationships;

use App\ConceptStatus;
use App\ContextKind;
use App\Models\Actor;
use App\Models\ActorProfileIntent;
use App\Models\Concept;
use App\Models\Context;
use App\Models\Relationship;
use App\Models\RelationshipContext;
use App\Models\RelationshipEvent;
use App\Models\RelationshipParticipant;
use App\Models\User;
use App\ProfileIntentStatus;
use App\RelationshipEventType;
use App\RelationshipParticipantStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class CreateRelationship
{
    /**
     * @param  list<array<string, mixed>>  $invitees
     */
    public function execute(
        User $user,
        Concept $purpose,
        string $creatorRole,
        array $invitees,
        ?ActorProfileIntent $originatingIntent = null,
        ?string $title = null,
    ): Relationship {
        $current = $this->currentUser($user);
        $creatorRole = $this->normalizeRole($creatorRole);
        abort_if($invitees === [] || count($invitees) > 20, 422, 'A Relationship requires between one and twenty invited participants.');

        return DB::transaction(function () use (
            $current,
            $purpose,
            $creatorRole,
            $invitees,
            $originatingIntent,
            $title,
        ): Relationship {
            $creator = Actor::query()->with('user')->lockForUpdate()->findOrFail($current->actor->id);
            $canonicalPurpose = Concept::query()->findOrFail($purpose->id)->canonical();

            abort_unless($canonicalPurpose->status === ConceptStatus::Active, 422, 'Relationship purpose must be an active Concept.');

            $participantSpecs = [];
            $seen = [$creator->id => true];

            foreach ($invitees as $spec) {
                $actor = $spec['actor'] ?? null;
                abort_unless($actor instanceof Actor, 422, 'Each Relationship participant must be an Actor.');

                $invitee = Actor::query()->with('user')->lockForUpdate()->findOrFail($actor->id);
                abort_if(isset($seen[$invitee->id]), 422, 'A Relationship cannot contain the same Actor twice.');
                abort_unless(
                    $invitee->status === 'active'
                    && $invitee->user instanceof User
                    && $invitee->user->status === 'active'
                    && $invitee->user->email_verified_at !== null,
                    422,
                    'Relationship invitations currently require active verified user-backed Actors.',
                );

                $seen[$invitee->id] = true;
                $participantSpecs[] = [
                    'actor' => $invitee,
                    'role' => $this->normalizeRole((string) ($spec['role'] ?? '')),
                    'can_manage' => (bool) ($spec['can_manage'] ?? false),
                ];
            }

            $lockedIntent = null;

            if ($originatingIntent instanceof ActorProfileIntent) {
                $lockedIntent = ActorProfileIntent::query()
                    ->with(['profile.actor', 'concept'])
                    ->lockForUpdate()
                    ->findOrFail($originatingIntent->id);

                Gate::forUser($current)->authorize('view', $lockedIntent);
                abort_unless($lockedIntent->status === ProfileIntentStatus::Active, 422, 'Only an active Intent can originate a new Relationship.');

                $intentConcept = $lockedIntent->concept->canonical();
                abort_unless(
                    (int) $intentConcept->id === (int) $canonicalPurpose->id,
                    422,
                    'Relationship purpose must match the originating Intent Concept.',
                );
                abort_unless(
                    isset($seen[$lockedIntent->profile->actor_id]),
                    422,
                    'The originating Intent owner must be a Relationship participant.',
                );
            }

            $relationship = Relationship::query()->create([
                'title' => $this->normalizeTitle($title),
                'purpose_concept_id' => $canonicalPurpose->id,
                'originating_intent_id' => $lockedIntent?->id,
                'created_by_actor_id' => $creator->id,
                'metadata' => [],
            ]);

            RelationshipParticipant::query()->create([
                'relationship_id' => $relationship->id,
                'actor_id' => $creator->id,
                'role' => $creatorRole,
                'status' => RelationshipParticipantStatus::Active,
                'can_manage' => true,
                'invited_by_actor_id' => $creator->id,
                'invited_at' => now(),
                'joined_at' => now(),
            ]);

            foreach ($participantSpecs as $spec) {
                RelationshipParticipant::query()->create([
                    'relationship_id' => $relationship->id,
                    'actor_id' => $spec['actor']->id,
                    'role' => $spec['role'],
                    'status' => RelationshipParticipantStatus::Invited,
                    'can_manage' => $spec['can_manage'],
                    'invited_by_actor_id' => $creator->id,
                    'invited_at' => now(),
                ]);
            }

            $context = Context::query()->create([
                'uuid' => (string) Str::uuid(),
                'kind' => ContextKind::Relationship,
            ]);

            RelationshipContext::query()->create([
                'context_id' => $context->id,
                'relationship_id' => $relationship->id,
            ]);

            RelationshipEvent::query()->create([
                'relationship_id' => $relationship->id,
                'actor_id' => $creator->id,
                'event_type' => RelationshipEventType::Proposed,
                'payload' => [
                    'purpose_concept_id' => $canonicalPurpose->id,
                    'originating_intent_id' => $lockedIntent?->id,
                    'invitee_actor_ids' => collect($participantSpecs)->pluck('actor.id')->values()->all(),
                ],
            ]);

            return $relationship->fresh([
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

    private function normalizeRole(string $role): string
    {
        $role = Str::squish($role);
        abort_if($role === '' || mb_strlen($role) > 80, 422, 'Relationship participant role must be between 1 and 80 characters.');

        return $role;
    }

    private function normalizeTitle(?string $title): ?string
    {
        $title = Str::squish((string) $title);

        if ($title === '') {
            return null;
        }

        abort_if(mb_strlen($title) > 180, 422, 'Relationship title may not exceed 180 characters.');

        return $title;
    }
}
