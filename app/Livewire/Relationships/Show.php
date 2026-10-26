<?php

namespace App\Livewire\Relationships;

use App\Actions\Relationships\CancelRelationship;
use App\Actions\Relationships\EndRelationship;
use App\Actions\Relationships\RespondToRelationship;
use App\Actions\Conversations\PostContextMessage;
use App\Actions\Deals\ProposeDealTerms;
use App\Actions\Messaging\ManageConversation;
use App\Actions\Proposals\CancelProposal;
use App\Actions\Proposals\RespondToProposal;
use App\Models\Actor;
use App\Models\Context;
use App\Models\Conversation;
use App\Models\Proposal;
use App\Models\ProposalVersion;
use App\ProposalDecisionKind;
use App\Support\Deals\DealOrigin;
use App\Support\Deals\DealTermsDiff;
use App\Support\Deals\DealTermsForm;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use App\Models\Relationship;
use App\Models\RelationshipParticipant;
use App\Models\User;
use App\Support\DealPipeline;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Deal')]
class Show extends Component
{
    public Relationship $relationship;

    public string $message = '';

    public string $sendUuid = '';

    /** The Terms version number on screen; every decision is bound to it. */
    public ?int $viewedVersion = null;

    public string $changeNote = '';

    public bool $editingTerms = false;

    /** @var array<string, string> */
    public array $terms = [];

    public function mount(Relationship $relationship): void
    {
        Gate::forUser($this->user())->authorize('view', $relationship);
        $this->relationship = $relationship;
        $this->sendUuid = (string) Str::uuid();
    }

    public function send(PostContextMessage $post, RespondToRelationship $respond): void
    {
        $text = trim($this->message);
        if ($text === '') {
            return;
        }

        // Replying is how an invited person joins the conversation.
        if (Gate::forUser($this->user())->allows('respond', $this->relationship)) {
            $this->relationship = $respond->execute($this->relationship, $this->user(), true);
        }

        $post->execute($this->context(), $this->user(), $text, clientUuid: $this->sendUuid);
        $this->reset('message');
        $this->sendUuid = (string) Str::uuid();
    }

    public function openTerms(): void
    {
        $this->terms = DealTermsForm::initial($this->relationship->fresh(['originatingIntent']), $this->latestVersion());
        $this->editingTerms = true;
    }

    public function closeTerms(): void
    {
        $this->editingTerms = false;
    }

    public function propose(ProposeDealTerms $propose): void
    {
        $this->validate(['terms.*' => ['nullable', 'string', 'max:2000'], 'changeNote' => ['nullable', 'string', 'max:1000']]);

        if (count(DealTermsForm::toTerms($this->terms)) === 1) {
            $this->addError('terms', __('deals.terms_empty'));

            return;
        }

        try {
            $version = $propose->execute($this->relationship, $this->user(), DealTermsForm::toTerms($this->terms), $this->changeNote !== '' ? $this->changeNote : null);
        } catch (HttpException $e) {
            if ($e->getStatusCode() !== 422) {
                throw $e;
            }
            $this->addError('terms', __('deals.terms_invalid'));

            return;
        }

        $this->note('terms_proposed', ['version' => $version->version, 'proposal' => $version->proposal->uuid]);
        $this->reset('editingTerms', 'changeNote', 'terms');
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
        $this->note('terms_withdrawn', ['proposal' => $proposal->uuid]);
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

        $this->note($type, ['version' => $this->viewedVersion, 'proposal' => $proposal->uuid]);
        $this->reset('changeNote');
    }

    /** A line in the conversation that records a structured step. Persisting it never decides the step. */
    private function note(string $type, array $meta): void
    {
        try {
            app(PostContextMessage::class)->execute($this->context(), $this->user(), (string) __('deals.timeline.'.$type, ['version' => $meta['version'] ?? '']), kind: 'deal', meta: ['type' => $type] + $meta);
        } catch (\Throwable $e) {
            report($e);
        }
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

    private function latestVersion(): ?ProposalVersion
    {
        return $this->latestProposal()?->currentVersionRecord();
    }

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
            ? DealOrigin::card($relationship->originatingIntent, \App\Models\IntentVersion::query()->find($relationship->originating_intent_version_id), $user)
            : null;

        $messages = collect();
        if ($context instanceof Context) {
            $conversation = Conversation::query()->where('context_id', $context->id)->where('key', 'main')->first();
            if ($conversation instanceof Conversation) {
                app(ManageConversation::class)->markRead($user, $conversation);
                $messages = $conversation->messages()->with(['author.user'])->latest('id')->limit(100)->get()->reverse()->values();
            }
        }

        $terms = $this->termsView($latestProposal, $user);
        $this->viewedVersion = $terms['version']?->version;

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
            'canTalk' => $context instanceof Context && Gate::forUser($user)->allows('interactContent', $context),
            'canPropose' => Gate::forUser($user)->allows('participate', $relationship),
            'context' => $context,
            'origin' => $origin,
            'messages' => $messages,
            'tc' => $terms,
        ]);
    }

    /** @return array<string, mixed> */
    private function termsView(?Proposal $proposal, User $user): array
    {
        $empty = ['proposal' => null, 'version' => null, 'diff' => null, 'parties' => collect(), 'mine' => null, 'canRespond' => false, 'canWithdraw' => false, 'open' => false, 'history' => collect()];
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

        $parties = $proposal->parties->map(function ($party) use ($current): array {
            $decision = $current?->decisions->firstWhere('proposal_party_id', $party->id);

            return ['party' => $party, 'decision' => $decision];
        });

        return [
            'proposal' => $proposal,
            'version' => $current,
            'diff' => $diff,
            'parties' => $parties,
            'canRespond' => Gate::forUser($user)->allows('respond', $proposal),
            'canWithdraw' => Gate::forUser($user)->allows('cancel', $proposal),
            'open' => $proposal->status === \App\ProposalStatus::Negotiating,
            'history' => $versions->slice(0, -1)->reverse()->values(),
        ];
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
