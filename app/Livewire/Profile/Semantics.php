<?php

namespace App\Livewire\Profile;

use App\Actions\Profile\AddProfileConceptAssertion;
use App\Actions\Profile\RemoveProfileConceptAssertion;
use App\Actions\Profile\UpdateProfileConceptAssertionProficiency;
use App\Actions\Profile\UpdateProfileConceptAssertionVisibility;
use App\ConceptAssertionPredicate;
use App\ConceptAssertionSubject;
use App\ConceptAssertionVisibility;
use App\Models\ActorProfile;
use App\Models\ConceptAssertion;
use App\Models\ConceptLabel;
use App\Models\User;
use App\Support\Profile\ProfileScale;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Semantics extends Component
{
    #[Locked]
    public ActorProfile $profile;

    public string $conceptLabel = '';

    public string $predicate = ConceptAssertionPredicate::HasSkill->value;

    public string $semanticVisibility = ConceptAssertionVisibility::Inherited->value;

    public ?string $proficiencyPercent = null;

    public ?int $editingProficiencyAssertionId = null;

    public ?string $editingProficiencyPercent = null;

    public bool $composerOpen = false;

    public function openComposer(): void
    {
        $this->reset(['conceptLabel']);
        $this->predicate = ConceptAssertionPredicate::HasSkill->value;
        $this->semanticVisibility = ConceptAssertionVisibility::Inherited->value;
        $this->proficiencyPercent = null;
        $this->resetValidation();
        $this->composerOpen = true;
    }

    public function cancelComposer(): void
    {
        $this->reset(['conceptLabel', 'proficiencyPercent']);
        $this->resetValidation();
        $this->composerOpen = false;
    }

    public function updatedPredicate(string $predicate): void
    {
        if ($predicate !== ConceptAssertionPredicate::HasSkill->value) {
            $this->proficiencyPercent = null;
        }
    }

    public function selectConceptSuggestion(string $label): void
    {
        $this->conceptLabel = trim($label);
    }

    public function add(AddProfileConceptAssertion $addAssertion): void
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        $data = $this->validate([
            'conceptLabel' => ['required', 'string', 'max:120'],
            'predicate' => [
                'required',
                Rule::in([
                    ConceptAssertionPredicate::HasSkill->value,
                    ConceptAssertionPredicate::InterestedIn->value,
                    ConceptAssertionPredicate::WantsToLearn->value,
                ]),
            ],
            'semanticVisibility' => [
                'required',
                Rule::in([
                    ConceptAssertionVisibility::Inherited->value,
                    ConceptAssertionVisibility::Private->value,
                ]),
            ],
            'proficiencyPercent' => ['nullable', 'integer', 'between:0,100'],
        ]);

        $addAssertion->execute(
            $user,
            $this->profile,
            $data['conceptLabel'],
            ConceptAssertionPredicate::from($data['predicate']),
            ConceptAssertionVisibility::from($data['semanticVisibility']),
            ($data['proficiencyPercent'] ?? null) === null || $data['proficiencyPercent'] === ''
                ? null
                : (int) $data['proficiencyPercent'],
        );

        $this->reset(['conceptLabel', 'proficiencyPercent']);
        $this->composerOpen = false;
        session()->flash('status', __('ui.profile.semantic_added'));
    }

    public function openProficiencyEditor(int $assertionId): void
    {
        $assertion = $this->assertions()->findOrFail($assertionId);
        abort_unless($assertion->predicate === ConceptAssertionPredicate::HasSkill, 404);

        $this->editingProficiencyAssertionId = $assertion->id;
        $percent = ProfileScale::percentFromWeight($assertion->weight);
        $this->editingProficiencyPercent = $percent === null ? null : (string) $percent;
        $this->resetValidation('editingProficiencyPercent');
    }

    public function cancelProficiencyEditor(): void
    {
        $this->editingProficiencyAssertionId = null;
        $this->editingProficiencyPercent = null;
        $this->resetValidation('editingProficiencyPercent');
    }

    public function saveProficiency(UpdateProfileConceptAssertionProficiency $updateProficiency): void
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);
        abort_unless($this->editingProficiencyAssertionId !== null, 422);

        $data = $this->validate([
            'editingProficiencyPercent' => ['nullable', 'integer', 'between:0,100'],
        ]);

        $assertion = $this->assertions()->findOrFail($this->editingProficiencyAssertionId);
        $value = $data['editingProficiencyPercent'] ?? null;

        $updateProficiency->execute(
            $user,
            $this->profile,
            $assertion,
            $value === null || $value === '' ? null : (int) $value,
        );

        $this->cancelProficiencyEditor();
    }

    public function setVisibility(
        int $assertionId,
        string $visibility,
        UpdateProfileConceptAssertionVisibility $updateVisibility,
    ): void {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        $validated = validator(
            ['visibility' => $visibility],
            ['visibility' => [Rule::in([
                ConceptAssertionVisibility::Inherited->value,
                ConceptAssertionVisibility::Private->value,
            ])]],
        )->validate();

        $assertion = $this->assertions()->findOrFail($assertionId);
        $updateVisibility->execute(
            $user,
            $this->profile,
            $assertion,
            ConceptAssertionVisibility::from($validated['visibility']),
        );
    }

    public function remove(int $assertionId, RemoveProfileConceptAssertion $removeAssertion): void
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        $assertion = $this->assertions()->findOrFail($assertionId);
        $removeAssertion->execute($user, $this->profile, $assertion);

        session()->flash('status', __('ui.profile.semantic_removed'));
    }

    public function render(): View
    {
        Gate::authorize('update', $this->profile);

        return view('livewire.profile.semantics', [
            'assertions' => $this->assertions()->with('concept.labels')->get(),
            'conceptSuggestions' => $this->conceptSuggestions(),
        ]);
    }

    /** @return list<string> */
    private function conceptSuggestions(): array
    {
        $term = trim($this->conceptLabel);

        if (mb_strlen($term) < 2) {
            return [];
        }

        $normalized = mb_strtolower($term);

        return ConceptLabel::query()
            ->where('normalized_label', 'like', '%'.$normalized.'%')
            ->whereHas('concept.vocabulary', function ($query): void {
                $query->where(function ($scope): void {
                    $scope->where('scope_type', 'platform')->where('scope_id', 0);
                })->orWhere(function ($scope): void {
                    $scope->where('scope_type', 'actor')->where('scope_id', $this->profile->actor_id);
                });
            })
            ->with('concept')
            ->limit(8)
            ->get()
            ->map(fn (ConceptLabel $label): string => $label->concept->displayLabel())
            ->unique()
            ->values()
            ->all();
    }

    /** @return Builder<ConceptAssertion> */
    private function assertions(): Builder
    {
        return ConceptAssertion::query()
            ->where('subject_type', ConceptAssertionSubject::Actor->value)
            ->where('subject_id', $this->profile->actor_id)
            ->whereIn('predicate', [
                ConceptAssertionPredicate::HasSkill->value,
                ConceptAssertionPredicate::InterestedIn->value,
                ConceptAssertionPredicate::WantsToLearn->value,
            ])
            ->orderBy('predicate')
            ->orderBy('id');
    }
}
