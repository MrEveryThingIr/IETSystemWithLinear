<?php

namespace App\Livewire\Profile;

use App\Actions\Profile\AddProfileConceptAssertion;
use App\Actions\Profile\RemoveProfileConceptAssertion;
use App\Actions\Profile\UpdateProfileConceptAssertionVisibility;
use App\ConceptAssertionPredicate;
use App\ConceptAssertionSubject;
use App\ConceptAssertionVisibility;
use App\Models\ActorProfile;
use App\Models\ConceptAssertion;
use App\Models\ConceptLabel;
use App\Models\User;
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

    public function selectConceptSuggestion(string $label): void
    {
        $this->conceptLabel = trim($label);
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
        ]);

        $addAssertion->execute(
            $user,
            $this->profile,
            $data['conceptLabel'],
            ConceptAssertionPredicate::from($data['predicate']),
            ConceptAssertionVisibility::from($data['semanticVisibility']),
        );

        $this->reset('conceptLabel');
        session()->flash('status', __('ui.profile.semantic_added'));
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
