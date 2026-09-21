<?php

namespace App\Livewire\Profile;

use App\ConceptAssertionPredicate;
use App\ConceptAssertionSubject;
use App\ConceptAssertionVisibility;
use App\Models\ActorProfile;
use App\Models\ConceptAssertion;
use App\Models\User;
use App\Policies\ActorProfileIntentPolicy;
use App\ProfileIntentStatus;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.public')]
#[Title('Profile')]
class Show extends Component
{
    #[Locked]
    public ActorProfile $profile;

    public function mount(ActorProfile $profile): void
    {
        $this->profile = $profile;
    }

    public function render(): View
    {
        Gate::authorize('view', $this->profile);

        $user = request()->user();
        $owner = $this->owns($user);
        $assertions = ConceptAssertion::query()
            ->where('subject_type', ConceptAssertionSubject::Actor->value)
            ->where('subject_id', $this->profile->actor_id)
            ->whereIn('predicate', [
                ConceptAssertionPredicate::HasSkill->value,
                ConceptAssertionPredicate::InterestedIn->value,
                ConceptAssertionPredicate::WantsToLearn->value,
            ])
            ->when(
                ! $owner,
                fn ($query) => $query->where('visibility', '!=', ConceptAssertionVisibility::Private->value),
            )
            ->with('concept.labels')
            ->orderBy('predicate')
            ->orderBy('id')
            ->get();

        $intentPolicy = app(ActorProfileIntentPolicy::class);
        $intents = $this->profile->intents()
            ->where('status', ProfileIntentStatus::Active->value)
            ->with('concept.labels')
            ->latest('updated_at')
            ->get()
            ->filter(fn ($intent): bool => $intentPolicy->view($user, $intent))
            ->values();

        return view('livewire.profile.show', [
            'profile' => $this->profile->load([
                'actor.user',
                'displayImage.asset',
            ]),
            'skills' => $assertions->where('predicate', ConceptAssertionPredicate::HasSkill)->values(),
            'interests' => $assertions->where('predicate', ConceptAssertionPredicate::InterestedIn)->values(),
            'learningGoals' => $assertions->where('predicate', ConceptAssertionPredicate::WantsToLearn)->values(),
            'needs' => $intents->where('kind.value', 'need')->values(),
            'offers' => $intents->where('kind.value', 'offer')->values(),
        ]);
    }

    private function owns(?User $user): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        $current = User::query()->with('actor')->find($user->id);

        return $current instanceof User
            && $current->actor !== null
            && (int) $current->actor->id === (int) $this->profile->actor_id;
    }
}
