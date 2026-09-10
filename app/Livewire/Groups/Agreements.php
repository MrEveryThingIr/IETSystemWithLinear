<?php

namespace App\Livewire\Groups;

use App\Actions\Groups\ManageGroupAgreement;
use App\Models\Actor;
use App\Models\Group;
use App\Models\GroupAgreement;
use App\Models\GroupAgreementVersion;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Agreements extends Component
{
    public Group $group;
    public string $name = ''; public string $content = ''; public string $rationale = ''; public string $decisionNote = ''; public string $effectiveFrom = ''; public string $effectiveUntil = ''; public bool $required = true; public bool $reacceptanceRequired = true;
    public function mount(Group $group): void { Gate::authorize('manageAgreements', $group); $this->group = $group; }
    public function create(ManageGroupAgreement $manager): void { Gate::authorize('manageAgreements', $this->group); $data = $this->validate(['name' => ['required', 'max:120'], 'content' => ['required', 'string'], 'required' => ['boolean']]); $manager->create($this->group, $this->actor(), $data['name'], $data['required'], $data['content']); $this->reset('name', 'content'); }
    public function revise(int $agreementId, ManageGroupAgreement $manager): void { Gate::authorize('manageAgreements', $this->group); $data = $this->validate(['content' => ['required', 'string'], 'rationale' => ['required', 'string'], 'reacceptanceRequired' => ['boolean']]); /** @var GroupAgreement $agreement */ $agreement = GroupAgreement::query()->where('group_id', $this->group->id)->findOrFail($agreementId); $manager->revise($agreement, $this->actor(), $data['content'], $data['rationale'], $data['reacceptanceRequired']); $this->reset('content', 'rationale'); }
    public function propose(int $versionId, ManageGroupAgreement $manager): void { Gate::authorize('manageAgreements', $this->group); $manager->propose($this->version($versionId), $this->actor()); }
    public function approve(int $versionId, ManageGroupAgreement $manager): void { Gate::authorize('manageAgreements', $this->group); $manager->approve($this->version($versionId), $this->actor()); }
    public function reject(int $versionId, ManageGroupAgreement $manager): void { Gate::authorize('manageAgreements', $this->group); $note = $this->validate(['decisionNote' => ['required', 'string']])['decisionNote']; $manager->reject($this->version($versionId), $this->actor(), $note); $this->reset('decisionNote'); }
    public function requestClarification(int $versionId, ManageGroupAgreement $manager): void { Gate::authorize('manageAgreements', $this->group); $note = $this->validate(['decisionNote' => ['required', 'string']])['decisionNote']; $manager->requestClarification($this->version($versionId), $this->actor(), $note); $this->reset('decisionNote'); }
    public function schedule(int $versionId, ManageGroupAgreement $manager): void { Gate::authorize('manageAgreements', $this->group); $data = $this->validate(['effectiveFrom' => ['required', 'date', 'after:now'], 'effectiveUntil' => ['nullable', 'date', 'after:effectiveFrom']]); $manager->schedule($this->version($versionId), $this->actor(), new \DateTimeImmutable($data['effectiveFrom']), empty($data['effectiveUntil']) ? null : new \DateTimeImmutable($data['effectiveUntil'])); $this->reset('effectiveFrom', 'effectiveUntil'); }
    public function render(): View { return view('livewire.groups.agreements', ['agreements' => GroupAgreement::query()->where('group_id', $this->group->id)->with('versions')->get()]); }
    private function version(int $versionId): GroupAgreementVersion { /** @var GroupAgreementVersion $version */ $version = GroupAgreementVersion::query()->whereHas('agreement', fn ($query) => $query->where('group_id', $this->group->id))->findOrFail($versionId); return $version; }
    private function actor(): Actor { /** @var Actor $actor */ $actor = Actor::query()->where('user_id', auth()->id())->firstOrFail(); return $actor; }
}
