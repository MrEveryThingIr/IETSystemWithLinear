<?php

namespace App\Livewire\Admissions;

use App\Actions\Groups\FinalizeAdmission;
use App\Actions\Groups\ManageAdmission;
use App\Models\Admission;
use App\Models\GroupAgreementVersion;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
    public Admission $admission;
    public string $note = '';
    public function mount(Admission $admission): void { Gate::authorize('view', $admission); $this->admission = $admission; }
    public function submit(ManageAdmission $manager): void { Gate::authorize('update', $this->admission); $manager->candidateTransition($this->admission, auth()->user()->actor, 'submitted', $this->note ?: null); }
    public function acceptVersion(int $versionId, ManageAdmission $manager): void { Gate::authorize('update', $this->admission); $manager->accept($this->admission, auth()->user()->actor, GroupAgreementVersion::findOrFail($versionId)); }
    public function review(string $status, ManageAdmission $manager): void { Gate::authorize('manageAdmissions', $this->admission->group); $manager->review($this->admission, auth()->user()->actor, $status, $this->note ?: null); }
    public function finalize(FinalizeAdmission $finalizer): void { Gate::authorize('manageAdmissions', $this->admission->group); $finalizer->execute($this->admission); }
    public function render(): View { $versions = GroupAgreementVersion::query()->whereHas('agreement', fn ($q) => $q->where('group_id', $this->admission->group_id))->get()->filter(fn ($v) => $v->isActiveAt()); return view('livewire.admissions.show', compact('versions')); }
}
