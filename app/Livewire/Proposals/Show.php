<?php

namespace App\Livewire\Proposals;

use App\Actions\Proposals\CancelProposal;
use App\Actions\Proposals\ProposeTermsVersion;
use App\Actions\Proposals\RespondToProposal;
use App\Models\Actor;
use App\Models\Proposal;
use App\Models\ProposalParty;
use App\Models\ProposalVersion;
use App\Models\User;
use App\ProposalDecisionKind;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Proposal')]
class Show extends Component
{
    public Proposal $proposal;

    public string $responseNote = '';

    /** The Terms version number last shown to this person; every decision is bound to it. */
    public ?int $viewedVersion = null;

    public string $revisionTitle = '';

    public string $revisionSummary = '';

    public string $revisionTerms = '';

    public string $revisionNotes = '';

    public string $versionNote = '';

    public function mount(Proposal $proposal): void
    {
        Gate::forUser($this->user())->authorize('view', $proposal);
        $this->proposal = $proposal;
    }

    public function accept(RespondToProposal $respond): void
    {
        $this->respondWith($respond, ProposalDecisionKind::Accepted, 'accepted');
    }

    public function requestChanges(RespondToProposal $respond): void
    {
        $this->validate(['responseNote' => ['required', 'string', 'max:2000']]);

        $this->respondWith($respond, ProposalDecisionKind::ChangesRequested, 'changes_requested');
    }

    public function reject(RespondToProposal $respond): void
    {
        $this->respondWith($respond, ProposalDecisionKind::Rejected, 'rejected');
    }

    /** Always answers for the version that was on screen; if Terms moved on meanwhile, nothing is recorded. */
    private function respondWith(RespondToProposal $respond, ProposalDecisionKind $kind, string $message): void
    {
        try {
            $respond->execute(
                $this->proposal,
                $this->user(),
                $kind,
                $this->responseNote !== '' ? $this->responseNote : null,
                $this->viewedVersion,
            );
        } catch (HttpException $e) {
            if ($e->getStatusCode() !== 409) {
                throw $e;
            }
            $this->refreshProposal();
            session()->flash('status', __('proposals.messages.terms_changed'));

            return;
        }

        $this->responseNote = '';
        $this->refreshProposal();
        session()->flash('status', __('proposals.messages.'.$message));
    }

    public function proposeVersion(ProposeTermsVersion $propose): void
    {
        Gate::forUser($this->user())->authorize('participate', $this->proposal);

        $this->validate([
            'revisionTitle' => ['required', 'string', 'max:255'],
            'revisionSummary' => ['nullable', 'string', 'max:10000'],
            'revisionTerms' => ['required', 'string', 'max:50000'],
            'revisionNotes' => ['nullable', 'string', 'max:10000'],
            'versionNote' => ['nullable', 'string', 'max:1000'],
        ]);

        $propose->execute(
            $this->proposal,
            $this->user(),
            $this->revisionTitle,
            $this->revisionTerms,
            $this->revisionSummary !== '' ? $this->revisionSummary : null,
            $this->revisionNotes !== '' ? $this->revisionNotes : null,
            $this->versionNote !== '' ? $this->versionNote : null,
        );

        $this->reset('revisionTitle', 'revisionSummary', 'revisionTerms', 'revisionNotes', 'versionNote');
        $this->refreshProposal();
        session()->flash('status', __('proposals.messages.version_proposed'));
    }

    public function cancel(CancelProposal $cancel): void
    {
        $this->proposal = $cancel->execute($this->proposal, $this->user());
        session()->flash('status', __('proposals.messages.cancelled'));
    }

    public function render(): View
    {
        $this->refreshProposal();

        $user = $this->user();
        $actor = $user->actor;
        abort_unless($actor instanceof Actor, 403);

        $party = $this->proposal->parties->firstWhere('actor_id', $actor->id);
        abort_unless($party instanceof ProposalParty, 403);

        $currentVersion = $this->proposal->versions->sortByDesc('version')->first();
        abort_unless($currentVersion instanceof ProposalVersion, 500);
        $this->viewedVersion = $currentVersion->version;

        $canRespond = Gate::forUser($user)->allows('respond', $this->proposal);
        $canParticipate = Gate::forUser($user)->allows('participate', $this->proposal);
        $canCancel = Gate::forUser($user)->allows('cancel', $this->proposal);
        $context = $this->proposal->contextBinding?->context;

        if ($canParticipate && $this->revisionTerms === '') {
            $this->revisionTitle = $currentVersion->termsRevision->title;
            $this->revisionSummary = (string) ($currentVersion->termsRevision->payload['summary'] ?? '');
            $this->revisionTerms = (string) ($currentVersion->termsRevision->payload['terms'] ?? '');
            $this->revisionNotes = (string) ($currentVersion->termsRevision->payload['notes'] ?? '');
        }

        return view('livewire.proposals.show', compact(
            'party',
            'currentVersion',
            'canRespond',
            'canParticipate',
            'canCancel',
            'context',
        ));
    }

    private function refreshProposal(): void
    {
        $proposal = Proposal::query()
            ->with([
                'relationship',
                'creator.user',
                'parties.actor.user',
                'contextBinding.context',
                'versions.termsRevision.content',
                'versions.proposedBy.user',
                'versions.decisions.party.actor.user',
                'versions.decisions.decidedByUser',
                'events.actor.user',
            ])
            ->findOrFail($this->proposal->id);

        Gate::forUser($this->user())->authorize('view', $proposal);
        $this->proposal = $proposal;
    }

    private function user(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
