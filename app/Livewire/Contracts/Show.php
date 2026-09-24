<?php

namespace App\Livewire\Contracts;

use App\Actions\Contracts\AcceptContractVersion;
use App\Actions\Contracts\ProposeContractAmendment;
use App\Models\Actor;
use App\Models\Contract;
use App\Models\ContractVersion;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

#[Layout('layouts.app')]
#[Title('Contract')]
class Show extends Component
{
    public Contract $contract;

    public string $amendmentTitle = '';

    public string $amendmentSummary = '';

    public string $amendmentTerms = '';

    public string $amendmentNotes = '';

    public string $versionNote = '';

    public string $effectiveAt = '';

    public string $timezone = 'UTC';

    public function mount(Contract $contract): void
    {
        Gate::forUser($this->user())->authorize('view', $contract);
        $this->contract = $contract;

        $user = $this->user();
        $this->timezone = $user->timezone ?: (string) config('app.timezone', 'UTC');
        $this->effectiveAt = CarbonImmutable::now($this->timezone)->addDay()->format('Y-m-d\TH:i');
    }

    public function accept(AcceptContractVersion $accept): void
    {
        $this->refreshContract();
        $version = $this->contract->pendingVersionRecord();
        abort_unless($version instanceof ContractVersion, 422);

        $accept->execute($version, $this->user());
        $this->refreshContract();

        session()->flash('status', __('contracts.messages.accepted'));
    }

    public function proposeAmendment(ProposeContractAmendment $amend): void
    {
        Gate::forUser($this->user())->authorize('amend', $this->contract);

        $data = $this->validate([
            'amendmentTitle' => ['required', 'string', 'max:255'],
            'amendmentSummary' => ['nullable', 'string', 'max:10000'],
            'amendmentTerms' => ['required', 'string', 'max:50000'],
            'amendmentNotes' => ['nullable', 'string', 'max:10000'],
            'versionNote' => ['nullable', 'string', 'max:1000'],
            'effectiveAt' => ['required', 'string', 'max:40'],
            'timezone' => ['required', 'string', 'max:64'],
        ]);

        $amend->execute(
            $this->contract,
            $this->user(),
            $data['amendmentTitle'],
            $data['amendmentTerms'],
            $this->effectiveInstant(),
            $data['timezone'],
            $data['amendmentSummary'] !== '' ? $data['amendmentSummary'] : null,
            $data['amendmentNotes'] !== '' ? $data['amendmentNotes'] : null,
            $data['versionNote'] !== '' ? $data['versionNote'] : null,
        );

        $this->reset('amendmentTitle', 'amendmentSummary', 'amendmentTerms', 'amendmentNotes', 'versionNote');
        $this->refreshContract();
        session()->flash('status', __('contracts.messages.amendment_proposed'));
    }

    public function render(): View
    {
        $this->refreshContract();

        $user = $this->user();
        $pendingVersion = $this->contract->pendingVersionRecord();
        $activeVersion = $this->contract->activeVersionRecord();

        $canAccept = $pendingVersion instanceof ContractVersion
            && Gate::forUser($user)->allows('accept', [$this->contract, $pendingVersion]);

        $canAmend = Gate::forUser($user)->allows('amend', $this->contract);
        $context = $this->contract->contextBinding?->context;

        if ($canAmend && $this->amendmentTerms === '' && $activeVersion instanceof ContractVersion) {
            $this->amendmentTitle = $activeVersion->termsRevision->title;
            $this->amendmentSummary = (string) ($activeVersion->termsRevision->payload['summary'] ?? '');
            $this->amendmentTerms = (string) ($activeVersion->termsRevision->payload['terms'] ?? '');
            $this->amendmentNotes = (string) ($activeVersion->termsRevision->payload['notes'] ?? '');
        }

        return view('livewire.contracts.show', compact(
            'pendingVersion',
            'activeVersion',
            'canAccept',
            'canAmend',
            'context',
        ));
    }

    private function refreshContract(): void
    {
        $contract = Contract::query()
            ->with([
                'relationship',
                'sourceProposalVersion.proposal',
                'sourceProposalVersion.termsRevision',
                'creator.user',
                'contextBinding.context',
                'versions.termsRevision.content',
                'versions.proposedBy.user',
                'versions.parties.actor.user',
                'versions.parties.acceptance.acceptedByUser',
                'events.actor.user',
            ])
            ->findOrFail($this->contract->id);

        Gate::forUser($this->user())->authorize('view', $contract);
        $this->contract = $contract;
    }

    private function effectiveInstant(): CarbonImmutable
    {
        try {
            return CarbonImmutable::parse($this->effectiveAt, $this->timezone)->utc();
        } catch (Throwable) {
            $this->addError('effectiveAt', __('validation.date', ['attribute' => __('contracts.create.effective_at')]));
            abort(422, 'Invalid Contract effective time.');
        }
    }

    private function user(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
