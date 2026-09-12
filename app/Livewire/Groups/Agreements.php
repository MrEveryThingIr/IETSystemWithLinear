<?php

namespace App\Livewire\Groups;

use App\Actions\Groups\ManageGroupAgreement;
use App\Models\Actor;
use App\Models\Group;
use App\Models\GroupAgreement;
use App\Models\GroupAgreementVersion;
use App\Support\LocalDateTime;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Agreements extends Component
{
    public Group $group;

    public string $name = '';

    public string $content = '';

    public ?int $revisingAgreementId = null;

    public string $revisionContent = '';

    public string $revisionRationale = '';

    public string $decisionNote = '';

    public string $effectiveFrom = '';

    public string $effectiveUntil = '';

    public bool $required = true;

    public bool $reacceptanceRequired = true;

    public function mount(Group $group): void
    {
        Gate::authorize('manageAgreements', $group);
        $this->group = $group;
    }

    public function create(ManageGroupAgreement $manager): void
    {
        Gate::authorize('manageAgreements', $this->group);
        $data = $this->validate(['name' => ['required', 'max:120'], 'content' => ['required', 'string'], 'required' => ['boolean']]);
        $manager->create($this->group, $this->actor(), $data['name'], $data['required'], $data['content']);
        $this->reset('name', 'content');
        session()->flash('status', __('ui.messages.agreement_created'));
    }

    public function startRevision(int $agreementId): void
    {
        Gate::authorize('manageAgreements', $this->group);
        GroupAgreement::query()->where('group_id', $this->group->id)->findOrFail($agreementId);
        $this->revisingAgreementId = $agreementId;
        $this->reset('revisionContent', 'revisionRationale');
        $this->reacceptanceRequired = true;
    }

    public function cancelRevision(): void
    {
        $this->reset('revisingAgreementId', 'revisionContent', 'revisionRationale');
        $this->reacceptanceRequired = true;
    }

    public function revise(int $agreementId, ManageGroupAgreement $manager): void
    {
        Gate::authorize('manageAgreements', $this->group);
        abort_unless($this->revisingAgreementId === $agreementId, 422);
        $data = $this->validate(['revisionContent' => ['required', 'string'], 'revisionRationale' => ['required', 'string'], 'reacceptanceRequired' => ['boolean']]);
        /** @var GroupAgreement $agreement */ $agreement = GroupAgreement::query()->where('group_id', $this->group->id)->findOrFail($agreementId);
        $manager->revise($agreement, $this->actor(), $data['revisionContent'], $data['revisionRationale'], $data['reacceptanceRequired']);
        $this->cancelRevision();
        session()->flash('status', __('ui.messages.agreement_revision_created'));
    }

    public function propose(int $versionId, ManageGroupAgreement $manager): void
    {
        Gate::authorize('manageAgreements', $this->group);
        $manager->propose($this->version($versionId), $this->actor());
        session()->flash('status', __('ui.messages.agreement_submitted'));
    }

    public function approve(int $versionId, ManageGroupAgreement $manager): void
    {
        Gate::authorize('manageAgreements', $this->group);
        $manager->approve($this->version($versionId), $this->actor());
        session()->flash('status', __('ui.messages.agreement_approved'));
    }

    public function reject(int $versionId, ManageGroupAgreement $manager): void
    {
        Gate::authorize('manageAgreements', $this->group);
        $note = $this->validate(['decisionNote' => ['required', 'string']])['decisionNote'];
        $manager->reject($this->version($versionId), $this->actor(), $note);
        $this->reset('decisionNote');
        session()->flash('status', __('ui.messages.agreement_rejected'));
    }

    public function requestClarification(int $versionId, ManageGroupAgreement $manager): void
    {
        Gate::authorize('manageAgreements', $this->group);
        $note = $this->validate(['decisionNote' => ['required', 'string']])['decisionNote'];
        $manager->requestClarification($this->version($versionId), $this->actor(), $note);
        $this->reset('decisionNote');
        session()->flash('status', __('ui.messages.agreement_clarification'));
    }

    public function schedule(int $versionId, ManageGroupAgreement $manager): void
    {
        Gate::authorize('manageAgreements', $this->group);
        $data = $this->validate(['effectiveFrom' => ['required', 'date_format:Y-m-d\\TH:i'], 'effectiveUntil' => ['nullable', 'date_format:Y-m-d\\TH:i']]);
        $timezone = $this->group->timezone ?: 'UTC';
        $from = LocalDateTime::toUtc($data['effectiveFrom'], $timezone, 'effectiveFrom');
        $until = empty($data['effectiveUntil']) ? null : LocalDateTime::toUtc($data['effectiveUntil'], $timezone, 'effectiveUntil');
        $manager->schedule($this->version($versionId), $this->actor(), $from, $until);
        $this->reset('effectiveFrom', 'effectiveUntil');
        session()->flash('status', __('ui.messages.agreement_scheduled'));
    }

    public function activate(int $versionId, ManageGroupAgreement $manager): void
    {
        Gate::authorize('manageAgreements', $this->group);
        $manager->activate($this->version($versionId), $this->actor());
        session()->flash('status', __('ui.messages.agreement_activated'));
    }

    public function render(): View
    {
        return view('livewire.groups.agreements', [
            'agreements' => GroupAgreement::query()
                ->where('group_id', $this->group->id)
                ->with(['versions' => fn ($query) => $query->orderByDesc('version')])
                ->orderBy('name')
                ->get(),
        ]);
    }

    private function version(int $versionId): GroupAgreementVersion
    {
        /** @var GroupAgreementVersion $version */
        $version = GroupAgreementVersion::query()->whereHas('agreement', fn ($query) => $query->where('group_id', $this->group->id))->findOrFail($versionId);

        return $version;
    }

    private function actor(): Actor
    {
        /** @var Actor $actor */
        $actor = Actor::query()->where('user_id', auth()->id())->firstOrFail();

        return $actor;
    }
}
