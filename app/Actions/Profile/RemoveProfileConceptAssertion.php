<?php

namespace App\Actions\Profile;

use App\ConceptAssertionPredicate;
use App\Models\ActorProfile;
use App\Models\ConceptAssertion;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class RemoveProfileConceptAssertion
{
    /** @var list<ConceptAssertionPredicate> */
    private const REMOVABLE = [
        ConceptAssertionPredicate::HasSkill,
        ConceptAssertionPredicate::InterestedIn,
        ConceptAssertionPredicate::WantsToLearn,
    ];

    public function execute(User $user, ActorProfile $profile, ConceptAssertion $assertion): void
    {
        Gate::forUser($user)->authorize('update', $profile);
        $profile->loadMissing('actor');

        abort_unless(
            $assertion->subject_type->value === 'actor'
            && (int) $assertion->subject_id === (int) $profile->actor_id
            && in_array($assertion->predicate, self::REMOVABLE, true),
            404,
        );

        Gate::forUser($user)->authorize('delete', $assertion);
        $assertion->delete();
    }
}
