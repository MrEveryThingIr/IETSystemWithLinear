<?php

namespace App\Actions\Profile;

use App\ConceptAssertionPredicate;
use App\ConceptAssertionSubject;
use App\Models\ActorProfile;
use App\Models\ConceptAssertion;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class UpdateProfileConceptAssertionProficiency
{
    public function execute(
        User $user,
        ActorProfile $profile,
        ConceptAssertion $assertion,
        ?int $proficiencyPercent,
    ): ConceptAssertion {
        Gate::forUser($user)->authorize('update', $profile);

        abort_unless(
            $assertion->subject_type === ConceptAssertionSubject::Actor
            && (int) $assertion->subject_id === (int) $profile->actor_id
            && $assertion->predicate === ConceptAssertionPredicate::HasSkill,
            404,
        );
        abort_if(
            $proficiencyPercent !== null
            && ($proficiencyPercent < 0 || $proficiencyPercent > 100),
            422,
            'Skill proficiency must be between 0 and 100.',
        );

        Gate::forUser($user)->authorize('update', $assertion);

        $assertion->weight = $proficiencyPercent === null
            ? null
            : $proficiencyPercent / 100;
        $assertion->save();

        return $assertion->refresh();
    }
}
