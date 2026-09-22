<?php

namespace App\Actions\Profile;

use App\Actions\Concepts\AssertConcept;
use App\ConceptAssertionPredicate;
use App\ConceptAssertionVisibility;
use App\Models\ActorProfile;
use App\Models\ConceptAssertion;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class AddProfileConceptAssertion
{
    /** @var list<ConceptAssertionPredicate> */
    private const ALLOWED = [
        ConceptAssertionPredicate::HasSkill,
        ConceptAssertionPredicate::InterestedIn,
        ConceptAssertionPredicate::WantsToLearn,
    ];

    public function execute(
        User $user,
        ActorProfile $profile,
        string $label,
        ConceptAssertionPredicate $predicate,
        ConceptAssertionVisibility $visibility = ConceptAssertionVisibility::Inherited,
        ?int $proficiencyPercent = null,
    ): ConceptAssertion {
        Gate::forUser($user)->authorize('update', $profile);
        abort_unless(in_array($predicate, self::ALLOWED, true), 422, 'Unsupported Profile semantic predicate.');
        abort_if($visibility === ConceptAssertionVisibility::Public, 422, 'Profile semantic visibility must inherit from the Profile or remain private.');
        abort_if(
            $proficiencyPercent !== null
            && $predicate !== ConceptAssertionPredicate::HasSkill,
            422,
            'Only skills can have Profile proficiency.',
        );
        abort_if(
            $proficiencyPercent !== null
            && ($proficiencyPercent < 0 || $proficiencyPercent > 100),
            422,
            'Skill proficiency must be between 0 and 100.',
        );

        $profile->loadMissing('actor');
        $concept = app(ResolveActorProfileConcept::class)->execute($user, $profile, $label);

        $assertion = app(AssertConcept::class)->execute(
            $user,
            $profile->actor,
            $concept,
            $predicate,
            visibility: $visibility,
            weight: $proficiencyPercent === null ? null : $proficiencyPercent / 100,
        );

        if ($predicate === ConceptAssertionPredicate::HasSkill && $proficiencyPercent !== null) {
            return app(UpdateProfileConceptAssertionProficiency::class)->execute(
                $user,
                $profile,
                $assertion,
                $proficiencyPercent,
            );
        }

        return $assertion;
    }
}
