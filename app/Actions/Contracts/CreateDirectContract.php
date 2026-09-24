<?php

namespace App\Actions\Contracts;

use App\ContextKind;
use App\ContractEventType;
use App\ContractStatus;
use App\Models\Actor;
use App\Models\Context;
use App\Models\Contract;
use App\Models\ContractContext;
use App\Models\ContractEvent;
use App\Models\Relationship;
use App\Models\RelationshipParticipant;
use App\Models\User;
use App\RelationshipParticipantStatus;
use App\RelationshipStatus;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class CreateDirectContract
{
    public function __construct(
        private readonly PublishContractTerms $terms,
        private readonly CreateContractVersion $versions,
    ) {}

    /**
     * @param  list<array{actor: Actor, role?: string, required?: bool}>  $invitees
     */
    public function execute(
        User $user,
        string $title,
        array $invitees,
        string $terms,
        CarbonInterface $effectiveFrom,
        string $effectiveTimezone,
        ?string $summary = null,
        ?string $notes = null,
        string $creatorRole = 'party',
        ?Relationship $relationship = null,
    ): Contract {
        $current = $this->currentUser($user);
        Gate::forUser($current)->authorize('create', Contract::class);

        $title = Str::squish($title);
        abort_if($title === '' || mb_strlen($title) > 180, 422, 'Contract title must be between 1 and 180 characters.');
        abort_if($invitees === [] || count($invitees) > 19, 422, 'A direct Contract requires between one and nineteen other parties.');

        return DB::transaction(function () use (
            $current,
            $title,
            $invitees,
            $terms,
            $effectiveFrom,
            $effectiveTimezone,
            $summary,
            $notes,
            $creatorRole,
            $relationship,
        ): Contract {
            $creator = Actor::query()->with('user')->lockForUpdate()->findOrFail($current->actor->id);
            $lockedRelationship = null;

            if ($relationship instanceof Relationship) {
                $lockedRelationship = Relationship::query()->lockForUpdate()->findOrFail($relationship->id);
                Gate::forUser($current)->authorize('participate', $lockedRelationship);
                abort_unless(
                    $lockedRelationship->status === RelationshipStatus::Active,
                    422,
                    'A sourced Contract requires an active Relationship.',
                );
            }

            $partySpecs = [[
                'actor' => $creator,
                'role' => $creatorRole,
                'required' => true,
            ]];

            $seen = [$creator->id => true];

            foreach ($invitees as $spec) {
                $party = Actor::query()->with('user')->lockForUpdate()->findOrFail($spec['actor']->id);

                abort_if(isset($seen[$party->id]), 422, 'A Contract cannot contain the same Actor twice.');
                abort_unless(
                    $party->status === 'active'
                    && $party->user instanceof User
                    && $party->user->status === 'active'
                    && $party->user->email_verified_at !== null,
                    422,
                    'Contract parties currently require active verified user-backed Actors.',
                );

                if ($lockedRelationship instanceof Relationship) {
                    abort_unless(
                        RelationshipParticipant::query()
                            ->where('relationship_id', $lockedRelationship->id)
                            ->where('actor_id', $party->id)
                            ->where('status', RelationshipParticipantStatus::Active->value)
                            ->exists(),
                        422,
                        'Every sourced Contract party must be an active Relationship participant.',
                    );
                }

                $seen[$party->id] = true;
                $partySpecs[] = [
                    'actor' => $party,
                    'role' => (string) ($spec['role'] ?? 'party'),
                    'required' => (bool) ($spec['required'] ?? true),
                ];
            }

            $contract = Contract::query()->create([
                'title' => $title,
                'relationship_id' => $lockedRelationship?->id,
                'source_proposal_version_id' => null,
                'created_by_actor_id' => $creator->id,
                'status' => ContractStatus::Pending,
            ]);

            $context = Context::query()->create(['kind' => ContextKind::Contract]);

            ContractContext::query()->create([
                'context_id' => $context->id,
                'contract_id' => $contract->id,
            ]);

            ContractEvent::query()->create([
                'contract_id' => $contract->id,
                'contract_version_id' => null,
                'actor_id' => $creator->id,
                'event_type' => ContractEventType::Created,
                'payload' => [
                    'relationship_uuid' => $lockedRelationship?->uuid,
                    'source_proposal_version_uuid' => null,
                ],
            ]);

            $revision = $this->terms->execute(
                $context,
                $current,
                $title.' — terms',
                $terms,
                $summary,
                $notes,
            );

            $this->versions->execute(
                $contract,
                $current,
                $revision,
                $partySpecs,
                $effectiveFrom,
                $effectiveTimezone,
                'Initial Contract terms',
            );

            return $contract->fresh([
                'relationship',
                'creator.user',
                'contextBinding.context',
                'versions.termsRevision.content',
                'versions.parties.actor.user',
                'versions.parties.acceptance',
                'events.actor.user',
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
