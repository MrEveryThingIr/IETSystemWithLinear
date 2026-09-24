<?php

namespace App\Actions\Proposals;

use App\ContextKind;
use App\Models\Actor;
use App\Models\Context;
use App\Models\Proposal;
use App\Models\ProposalContext;
use App\Models\ProposalEvent;
use App\Models\ProposalParty;
use App\Models\Relationship;
use App\Models\RelationshipParticipant;
use App\Models\User;
use App\ProposalEventType;
use App\ProposalStatus;
use App\RelationshipParticipantStatus;
use App\RelationshipStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class CreateProposal
{
    public function __construct(private readonly ProposeTermsVersion $versions) {}

    /**
     * @param  list<array{actor: Actor, role?: string, required?: bool}>  $invitees
     */
    public function execute(
        User $user,
        string $title,
        array $invitees,
        string $terms,
        ?string $summary = null,
        ?string $notes = null,
        string $creatorRole = 'proposer',
        ?Relationship $relationship = null,
    ): Proposal {
        $current = $this->currentUser($user);
        Gate::forUser($current)->authorize('create', Proposal::class);

        $title = Str::squish($title);
        abort_if($title === '' || mb_strlen($title) > 180, 422, 'Proposal title must be between 1 and 180 characters.');
        abort_if($invitees === [] || count($invitees) > 20, 422, 'A Proposal requires between one and twenty other parties.');

        return DB::transaction(function () use (
            $current,
            $title,
            $invitees,
            $terms,
            $summary,
            $notes,
            $creatorRole,
            $relationship,
        ): Proposal {
            $creator = Actor::query()->with('user')->lockForUpdate()->findOrFail($current->actor->id);
            $lockedRelationship = null;

            if ($relationship instanceof Relationship) {
                $lockedRelationship = Relationship::query()->lockForUpdate()->findOrFail($relationship->id);
                Gate::forUser($current)->authorize('participate', $lockedRelationship);
                abort_unless(
                    $lockedRelationship->status === RelationshipStatus::Active,
                    422,
                    'A sourced Proposal requires an active Relationship.',
                );
            }

            $partySpecs = [];
            $seen = [$creator->id => true];

            foreach ($invitees as $spec) {
                $actor = $spec['actor'] ?? null;
                abort_unless($actor instanceof Actor, 422, 'Each Proposal party must be an Actor.');

                $party = Actor::query()->with('user')->lockForUpdate()->findOrFail($actor->id);
                abort_if(isset($seen[$party->id]), 422, 'A Proposal cannot contain the same Actor twice.');
                abort_unless(
                    $party->status === 'active'
                    && $party->user instanceof User
                    && $party->user->status === 'active'
                    && $party->user->email_verified_at !== null,
                    422,
                    'Proposal parties currently require active verified user-backed Actors.',
                );

                if ($lockedRelationship instanceof Relationship) {
                    abort_unless(
                        RelationshipParticipant::query()
                            ->where('relationship_id', $lockedRelationship->id)
                            ->where('actor_id', $party->id)
                            ->where('status', RelationshipParticipantStatus::Active->value)
                            ->exists(),
                        422,
                        'Every sourced Proposal party must be an active participant in the Relationship.',
                    );
                }

                $seen[$party->id] = true;
                $partySpecs[] = [
                    'actor' => $party,
                    'role' => $this->normalizeRole((string) ($spec['role'] ?? 'party')),
                    'required' => (bool) ($spec['required'] ?? true),
                ];
            }

            $proposal = Proposal::query()->create([
                'title' => $title,
                'relationship_id' => $lockedRelationship?->id,
                'created_by_actor_id' => $creator->id,
                'status' => ProposalStatus::Negotiating,
            ]);

            ProposalParty::query()->create([
                'proposal_id' => $proposal->id,
                'actor_id' => $creator->id,
                'role' => $this->normalizeRole($creatorRole),
                'required' => true,
                'added_by_actor_id' => $creator->id,
            ]);

            foreach ($partySpecs as $spec) {
                ProposalParty::query()->create([
                    'proposal_id' => $proposal->id,
                    'actor_id' => $spec['actor']->id,
                    'role' => $spec['role'],
                    'required' => $spec['required'],
                    'added_by_actor_id' => $creator->id,
                ]);
            }

            $context = Context::query()->create(['kind' => ContextKind::Negotiation]);

            ProposalContext::query()->create([
                'context_id' => $context->id,
                'proposal_id' => $proposal->id,
            ]);

            ProposalEvent::query()->create([
                'proposal_id' => $proposal->id,
                'proposal_version_id' => null,
                'actor_id' => $creator->id,
                'event_type' => ProposalEventType::Created,
                'payload' => [
                    'relationship_uuid' => $lockedRelationship?->uuid,
                    'party_actor_ids' => collect($partySpecs)->pluck('actor.id')->values()->all(),
                ],
            ]);

            $this->versions->execute(
                $proposal,
                $current,
                $title.' — terms',
                $terms,
                $summary,
                $notes,
                'Initial proposal terms',
            );

            return $proposal->fresh([
                'relationship',
                'creator.user',
                'parties.actor.user',
                'contextBinding.context',
                'versions.termsRevision.content',
                'versions.decisions.party.actor.user',
                'events.actor.user',
            ]);
        }, attempts: 3);
    }

    private function normalizeRole(string $role): string
    {
        $role = Str::squish($role);
        abort_if($role === '' || mb_strlen($role) > 80, 422, 'Proposal party role must be between 1 and 80 characters.');

        return $role;
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
