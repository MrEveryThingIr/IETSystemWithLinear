<?php

namespace App\Actions\Deals;

use App\Actions\Proposals\CreateProposal;
use App\Actions\Proposals\ProposeTermsVersion;
use App\Models\Actor;
use App\Models\IntentVersion;
use App\Models\Proposal;
use App\Models\ProposalVersion;
use App\Models\Relationship;
use App\Models\RelationshipParticipant;
use App\Models\User;
use App\ProposalStatus;
use App\RelationshipParticipantStatus;
use App\Support\Deals\AgreementDrafter;
use App\Support\Deals\DealPrincipals;
use Illuminate\Support\Facades\Gate;

/**
 * "Propose terms" from the Deal workspace: the first call opens the Proposal, later calls add a new immutable version.
 * Parties come from the Deal and the pinned Need/Offer, never from the form.
 */
class ProposeDealTerms
{
    public function __construct(private readonly CreateProposal $create, private readonly ProposeTermsVersion $version) {}

    /** @param array<string, mixed> $terms schema-1 Terms without origin (the pinned origin is added here) */
    public function execute(Relationship $relationship, User $user, array $terms, ?string $note = null): ProposalVersion
    {
        $deal = Relationship::query()->with(['originatingIntent.profile', 'matchedIntent.profile', 'participants'])->findOrFail($relationship->id);
        Gate::forUser($user)->authorize('participate', $deal);
        abort_unless(AgreementDrafter::canDraft($deal, $user), 403); // others ask for an agreement or ask for changes

        $terms['origin'] = $this->origin($deal);
        $title = ($terms['title'] ?? null) ?: $deal->title ?: ($terms['origin']['title'] ?? $deal->purposeConcept?->displayLabel() ?? 'Deal');
        unset($terms['origin']['title']);

        $proposal = Proposal::query()->where('relationship_id', $deal->id)->latest('id')->first();

        if ($proposal instanceof Proposal && $proposal->status === ProposalStatus::Negotiating) {
            return $this->version->execute($proposal, $user, $title, '', null, null, $note, $terms);
        }

        $me = $user->actor;
        $invitees = $deal->participants
            ->filter(fn (RelationshipParticipant $p): bool => $p->status === RelationshipParticipantStatus::Active && (int) $p->actor_id !== (int) $me->id)
            ->map(fn (RelationshipParticipant $p): array => [
                'actor' => $p->actor,
                'party' => DealPrincipals::forActor($deal, $p->actor),
                'role' => $p->role,
            ])
            ->values()
            ->all();

        $created = $this->create->execute($user, $title, $invitees, '', null, null, $this->roleOf($deal, $me), $deal, $terms, DealPrincipals::forActor($deal, $me));

        return $created->versions()->latest('version')->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function origin(Relationship $deal): array
    {
        $intent = $deal->originatingIntent;
        $version = $deal->originating_intent_version_id ? IntentVersion::query()->find($deal->originating_intent_version_id) : null;
        if ($intent === null || $version === null) {
            return [];
        }

        return [
            'intent_uuid' => $intent->uuid,
            'intent_version_id' => $version->id,
            'kind' => $intent->kind->value,
            'title' => $version->snapshot['core']['title'] ?? null,
        ];
    }

    private function roleOf(Relationship $deal, Actor $actor): string
    {
        return (string) ($deal->participants->firstWhere('actor_id', $actor->id)?->role ?: 'proposer');
    }
}
