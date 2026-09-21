<?php

namespace App\Livewire\Admissions;

use App\Actions\Groups\FinalizeAdmission;
use App\Actions\Groups\ManageAdmission;
use App\Models\Admission;
use App\Models\GroupAgreementVersion;
use App\Support\AgreementEvidence;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Show extends Component
{
    public Admission $admission;

    public string $note = '';

    public function mount(Admission $admission): void
    {
        Gate::authorize('view', $admission);
        $this->admission = $admission->load(['group', 'candidate.user']);
    }

    public function submit(ManageAdmission $manager): void
    {
        Gate::authorize('update', $this->admission);
        $manager->candidateTransition($this->admission, auth()->user()->actor, 'submitted', $this->note ?: null);
        $this->reset('note');
        $this->admission->refresh();
    }

    public function cancel(ManageAdmission $manager): void
    {
        Gate::authorize('update', $this->admission);
        $manager->candidateTransition($this->admission, auth()->user()->actor, 'cancelled', $this->note ?: null);
        $this->reset('note');
        $this->admission->refresh();
    }

    public function acceptVersion(int $versionId, ManageAdmission $manager): void
    {
        Gate::authorize('update', $this->admission);
        $manager->accept($this->admission, auth()->user()->actor, GroupAgreementVersion::findOrFail($versionId));
        $this->admission->refresh();
    }

    public function review(string $status, ManageAdmission $manager): void
    {
        Gate::authorize('manageAdmissions', $this->admission->group);
        $manager->review($this->admission, auth()->user()->actor, $status, $this->note ?: null);
        $this->reset('note');
        $this->admission->refresh();
    }

    public function finalize(FinalizeAdmission $finalizer): void
    {
        Gate::authorize('manageAdmissions', $this->admission->group);
        $membership = $finalizer->execute($this->admission);
        $this->redirectRoute('groups.show', ['group' => $membership->group_id]);
    }

    public function render(): View
    {
        $versions = GroupAgreementVersion::query()
            ->with('agreement')
            ->whereHas('agreement', fn ($query) => $query->where('group_id', $this->admission->group_id)->where('required_for_admission', true))
            ->orderBy('group_agreement_id')
            ->orderByDesc('version')
            ->get()
            ->filter(fn (GroupAgreementVersion $version): bool => $version->isActiveAt());
        $acceptances = $this->admission->acceptances()->where('accepted_by_actor_id', $this->admission->candidate_actor_id)->get()->keyBy('group_agreement_version_id');
        $acceptedVersionIds = $versions->filter(function (GroupAgreementVersion $version) use ($acceptances): bool {
            $acceptance = $acceptances->get($version->id);

            return $acceptance !== null && AgreementEvidence::matchesAdmissionAcceptance($acceptance, $version, $this->admission->candidate);
        })->pluck('id');
        $allAgreementsAccepted = $acceptedVersionIds->count() === $versions->count();
        $events = $this->admission->events()->with('actor.user')->oldest()->get();

        return view('livewire.admissions.show', compact('versions', 'acceptedVersionIds', 'allAgreementsAccepted', 'events'));
    }
}
