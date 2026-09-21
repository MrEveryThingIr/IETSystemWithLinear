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
    ): ConceptAssertion {
        Gate::forUser($user)->authorize('update', $profile);
        abort_unless(in_array($predicate, self::ALLOWED, true), 422, 'Unsupported Profile semantic predicate.');
        abort_if($visibility === ConceptAssertionVisibility::Public, 422, 'Profile semantic visibility must inherit from the Profile or remain private.');

        $profile->loadMissing('actor');
        $concept = app(ResolveActorProfileConcept::class)->execute($user, $profile, $label);

        return app(AssertConcept::class)->execute(
            $user,
            $profile->actor,
            $concept,
            $predicate,
            visibility: $visibility,
        );
    }
}
