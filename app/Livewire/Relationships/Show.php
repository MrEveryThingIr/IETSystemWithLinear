<?php

namespace App\Livewire\Relationships;

use App\Actions\Proposals\CancelProposal;
use App\Actions\Proposals\RespondToProposal;
use App\Actions\Relationships\CancelRelationship;
use App\Actions\Relationships\EndRelationship;
use App\Actions\Relationships\RespondToRelationship;
use App\Models\Actor;
use App\Models\Context;
use App\Models\IntentVersion;
use App\Models\Proposal;
use App\Models\Relationship;
use App\Models\RelationshipParticipant;
use App\Models\User;
use App\ProposalDecisionKind;
use App\ProposalStatus;
use App\RelationshipStatus;
use App\Support\Deals\AgreementDocument;
use App\Support\Deals\AgreementDrafter;
use App\Support\DealPipeline;
use App\Support\Deals\DealNote;
use App\Support\Deals\DealOrigin;
use App\Support\Deals\DealTermsDiff;
use App\Support\Messaging\ThreadFor;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Attributes\Title;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * One Deal workspace: the original Need/Offer, the shared message thread, the Terms card and the Agreement.
 * Messages are ordinary talk; only the Terms actions below create binding decisions, each on the exact version shown.
 */
#[Layout('layouts.app')]
#[Title('Deal')]
class Show extends Component
{
    public Relationship $relationship;

    /** The Terms version number on screen; every decision is bound to it. */
    public ?int $viewedVersion = null;

    public string $changeNote = '';

    public string $requestNote = '';

    /** Deep links: ?tab=agreement&v=2 opens the Agreement tab with that version expanded. */
    #[Url(as: 'tab')]
    public string $tab = '';

    #[Url(as: 'v')]
    public ?int $v = null;

    /** ?resume=1 reopens the saved Agreement draft right away (the Resume editing links). */
    #[Url(as: 'resume', except: false)]
    public bool $resume = false;

    public function mount(Relationship $relationship): void
    {
        Gate::forUser($this->user())->authorize('view', $relationship);
        $this->relationship = $relationship;
    }

    /** The Terms composer changed something: re-render with fresh data. */
    #[On('deal-changed')]
    public function refreshDeal(): void {}

    public function accept(RespondToRelationship $respond): void
    {
        $this->relationship = $respond->execute($this->relationship, $this->user(), true);
        session()->flash('status', __('relationships.messages.accepted'));
    }

    public function decline(RespondToRelationship $respond): void
    {
        $this->relationship = $respond->execute($this->relationship, $this->user(), false);
        session()->flash('status', __('relationships.messages.declined'));
    }

    public function cancel(CancelRelationship $cancel): void
    {
        $this->relationship = $cancel->execute($this->relationship, $this->user());
        session()->flash('status', __('relationships.messages.cancelled'));
    }

    public function end(EndRelationship $end): void
    {
        $this->relationship = $end->execute($this->relationship, $this->user());
        session()->flash('status', __('relationships.messages.ended'));
    }

    /** Someone who may not draft asks the drafter to write the Agreement. It is a request, never a term. */
    public function requestAgreement(): void
    {
        $deal = $this->freshDeal();
        Gate::forUser($this->user())->authorize('participate', $deal);
        abort_if(AgreementDrafter::canDraft($deal, $this->user()), 422);

        $meta = $deal->metadata ?? [];
        $requests = collect($meta['agreement_requests'] ?? [])->reject(fn ($r) => (int) $r['actor_id'] === (int) $this->user()->actor->id)->values()->all();
        $requests[] = ['actor_id' => $this->user()->actor->id, 'note' => mb_substr(trim($this->requestNote), 0, 1000), 'at' => now()->toIso8601String()];
        $meta['agreement_requests'] = $requests;
        $deal->update(['metadata' => $meta]);

        DealNote::post($this->context(), $this->user(), 'agreement_requested');
        $this->reset('requestNote');
        $this->relationship = $this->freshDeal();
    }

    /** The owner side lets another active participant write the Agreement (or takes drafting back with 0). */
    public function delegateDrafting(int $actorId): void
    {
        AgreementDrafter::delegate($this->freshDeal(), $this->user(), $actorId > 0 ? $actorId : null);
        $this->relationship = $this->freshDeal();
    }

    public function agree(RespondToProposal $respond): void
    {
        $this->decide($respond, ProposalDecisionKind::Accepted, 'agreed');
    }

    public function requestChanges(RespondToProposal $respond): void
    {
        $this->decide($respond, ProposalDecisionKind::ChangesRequested, 'changes_requested');
    }

    public function declineTerms(RespondToProposal $respond): void
    {
        $this->decide($respond, ProposalDecisionKind::Rejected, 'terms_declined');
    }

    public function withdrawTerms(CancelProposal $cancel): void
    {
        $proposal = $this->latestProposal();
        abort_unless($proposal instanceof Proposal, 404);
        $cancel->execute($proposal, $this->user());
        DealNote::post($this->context(), $this->user(), 'terms_withdrawn', ['proposal' => $proposal->uuid]);
    }

    private function decide(RespondToProposal $respond, ProposalDecisionKind $kind, string $type): void
    {
        $proposal = $this->latestProposal();
        abort_unless($proposal instanceof Proposal, 404);

        try {
            $respond->execute($proposal, $this->user(), $kind, $this->changeNote !== '' ? $this->changeNote : null, $this->viewedVersion);
        } catch (HttpException $e) {
            if ($e->getStatusCode() !== 409) {
                throw $e;
            }
            $this->addError('terms', __('deals.terms_changed'));

            return;
        }

        DealNote::post($this->context(), $this->user(), $type, ['version' => $this->viewedVersion, 'proposal' => $proposal->uuid]);
        $this->reset('changeNote');
    }

    public function render(DealPipeline $pipeline): View
    {
        $user = $this->user();

        $relationship = Relationship::query()
            ->with([
                'purposeConcept.labels',
                'domainBlueprintVersion.blueprint',
                'originatingIntent.profile.actor.user',
                'matchedIntent.profile.actor.user',
                'participants.actor.user',
                'events.actor.user',
                'contextBinding.context',
                'proposals.versions.derivedContract',
                'contracts.versions.commitments.fulfillments',
            ])
            ->findOrFail($this->relationship->id);

        Gate::forUser($user)->authorize('view', $relationship);
        $this->relationship = $relationship;

        $participant = RelationshipParticipant::query()
            ->where('relationship_id', $relationship->id)
            ->where('actor_id', $user->actor->id)
            ->firstOrFail();

        $latestProposal = $relationship->proposals->sortByDesc('id')->first();
        $latestContract = $relationship->contracts->sortByDesc('id')->first();
        $context = $relationship->contextBinding?->context;

        $origin = $relationship->originatingIntent && $relationship->originating_intent_version_id
            ? DealOrigin::card($relationship->originatingIntent, IntentVersion::query()->find($relationship->originating_intent_version_id), $user)
            : null;

        $tc = $this->termsView($latestProposal, $user);
        $this->viewedVersion = $tc['version']?->version;

        return view('livewire.relationships.show', [
            'participant' => $participant,
            'dealStage' => $pipeline->stage($relationship),
            'dealSteps' => $pipeline->steps(),
            'dealNextAction' => $pipeline->nextAction($relationship),
            'latestProposal' => $latestProposal,
            'latestContract' => $latestContract,
            'canRespond' => Gate::forUser($user)->allows('respond', $relationship),
            'canCancel' => Gate::forUser($user)->allows('cancel', $relationship),
            'canEnd' => Gate::forUser($user)->allows('end', $relationship),
            'canPropose' => Gate::forUser($user)->allows('participate', $relationship),
            'context' => $context,
            'conversation' => $context instanceof Context ? ThreadFor::conversation($context, $user) : null,
            'introAuthor' => $relationship->participants->firstWhere('actor_id', $relationship->created_by_actor_id)?->actor,
            'origin' => $origin,
            'tc' => $tc,
            'doc' => $tc['version']?->terms_data !== null ? AgreementDocument::sections($tc['version']->terms_data) : [],
            'canDraft' => AgreementDrafter::canDraft($relationship, $user) && $relationship->status === RelationshipStatus::Active,
            'isOwnerSide' => AgreementDrafter::isOwnerSide($relationship, $user),
            'drafterActorId' => AgreementDrafter::delegateActorId($relationship) ?? AgreementDrafter::ownerActorId($relationship),
            'hasDraft' => isset($relationship->metadata['agreement_draft']),
            'requests' => $this->requests($relationship),
            'startTab' => in_array($this->tab, ['conversation', 'agreement', 'work', 'money'], true) ? $this->tab : $this->startTab($relationship, $tc, $user),
            'focusVersion' => $this->v,
            'autostart' => $this->resume,
        ]);
    }

    /** @return list<array{actor: ?Actor, note: string}> */
    private function requests(Relationship $deal): array
    {
        return collect($deal->metadata['agreement_requests'] ?? [])->map(fn ($r): array => [
            'actor' => $deal->participants->firstWhere('actor_id', (int) $r['actor_id'])?->actor,
            'note' => (string) ($r['note'] ?? ''),
        ])->all();
    }

    /** Conversation unless something in the Agreement waits for this person. */
    private function startTab(Relationship $deal, array $tc, User $user): string
    {
        $mine = $tc['open'] && $tc['canRespond'];
        $drafting = $deal->status === RelationshipStatus::Active && AgreementDrafter::canDraft($deal, $user) && (isset($deal->metadata['agreement_draft']) || ! empty($deal->metadata['agreement_requests']));

        return $mine || $drafting ? 'agreement' : 'conversation';
    }

    /** @return array<string, mixed> */
    private function termsView(?Proposal $proposal, User $user): array
    {
        $empty = ['proposal' => null, 'version' => null, 'rows' => [], 'diff' => null, 'parties' => collect(), 'canRespond' => false, 'canWithdraw' => false, 'open' => false, 'history' => collect()];
        if (! $proposal instanceof Proposal) {
            return $empty;
        }

        $proposal->load(['parties.actor.user', 'parties.business', 'parties.group', 'versions.termsRevision', 'versions.decisions', 'versions.decisions.decidedByUser']);
        $versions = $proposal->versions->sortBy('version')->values();
        $current = $versions->last();
        $previous = $versions->count() > 1 ? $versions[$versions->count() - 2] : null;
        $diff = $current && $current->terms_data !== null && $previous?->terms_data !== null
            ? DealTermsDiff::compare($previous->terms_data, $current->terms_data)
            : null;

        return [
            'proposal' => $proposal,
            'version' => $current,
            'rows' => $current?->terms_data !== null ? DealTermsDiff::rows($current->terms_data) : [],
            'diff' => $diff,
            'parties' => $proposal->parties->map(fn ($party): array => ['party' => $party, 'decision' => $current?->decisions->firstWhere('proposal_party_id', $party->id)]),
            'canRespond' => Gate::forUser($user)->allows('respond', $proposal),
            'canWithdraw' => Gate::forUser($user)->allows('cancel', $proposal),
            'open' => $proposal->status === ProposalStatus::Negotiating,
            'history' => $versions->slice(0, -1)->reverse()->values(),
        ];
    }

    private function freshDeal(): Relationship
    {
        return Relationship::query()->with(['originatingIntent.profile', 'participants.actor.user', 'contextBinding.context'])->findOrFail($this->relationship->id);
    }

    private function context(): Context
    {
        $context = $this->relationship->contextBinding?->context;
        abort_unless($context instanceof Context, 404);

        return $context;
    }

    private function latestProposal(): ?Proposal
    {
        return Proposal::query()->where('relationship_id', $this->relationship->id)->latest('id')->first();
    }

    private function user(): User
    {
        $user = request()->user();
        abort_unless(
            $user instanceof User
            && $user->actor instanceof Actor
            && $user->status === 'active'
            && $user->email_verified_at !== null
            && $user->actor->status === 'active',
            403,
        );

        return $user;
    }
}
