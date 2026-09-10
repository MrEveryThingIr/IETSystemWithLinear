<?php

namespace App\Livewire\Groups;

use App\Actions\Groups\ManageGroupAgreement;
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
    public string $name = '';
    public string $content = '';
    public bool $required = true;
    public function mount(Group $group): void { Gate::authorize('manageAgreements', $group); $this->group = $group; }
    public function create(ManageGroupAgreement $manager): void { $data = $this->validate(['name' => ['required','max:120'], 'content' => ['required','string'], 'required' => ['boolean']]); $manager->create($this->group, auth()->user()->actor, $data['name'], $data['required'], $data['content']); $this->reset('name', 'content'); }
    public function revise(int $agreementId, ManageGroupAgreement $manager): void { $this->validate(['content' => ['required','string']]); $manager->revise($this->group->agreements()->findOrFail($agreementId), auth()->user()->actor, $this->content); $this->reset('content'); }
    public function activate(int $versionId, ManageGroupAgreement $manager): void { $version = GroupAgreementVersion::query()->whereHas('agreement', fn ($q) => $q->where('group_id', $this->group->id))->findOrFail($versionId); $manager->schedule($version, now(), null); }
    public function render(): View { return view('livewire.groups.agreements', ['agreements' => $this->group->agreements()->with('versions')->get()]); }
}
