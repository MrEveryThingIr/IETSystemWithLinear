<?php

namespace App\Actions\Profile;

use App\ConceptAssertionPredicate;
use App\ConceptAssertionSubject;
use App\ConceptAssertionVisibility;
use App\Models\ActorProfile;
use App\Models\ConceptAssertion;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class UpdateProfileConceptAssertionVisibility
{
    /** @var list<ConceptAssertionPredicate> */
    private const EDITABLE = [
        ConceptAssertionPredicate::HasSkill,
        ConceptAssertionPredicate::InterestedIn,
        ConceptAssertionPredicate::WantsToLearn,
    ];

    public function execute(
        User $user,
        ActorProfile $profile,
        ConceptAssertion $assertion,
        ConceptAssertionVisibility $visibility,
    ): ConceptAssertion {
        Gate::forUser($user)->authorize('update', $profile);
        abort_if($visibility === ConceptAssertionVisibility::Public, 422, 'Profile semantic visibility must inherit from the Profile or remain private.');
        abort_unless(
            $assertion->subject_type === ConceptAssertionSubject::Actor
            && (int) $assertion->subject_id === (int) $profile->actor_id
            && in_array($assertion->predicate, self::EDITABLE, true),
            404,
        );

        Gate::forUser($user)->authorize('update', $assertion);
        $assertion->visibility = $visibility;
        $assertion->save();

        return $assertion->refresh();
    }
}
