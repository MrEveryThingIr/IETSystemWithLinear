<?php

namespace App\Actions\Profile;

use App\Actions\Concepts\AssertConcept;
use App\ConceptAssertionPredicate;
use App\ConceptAssertionSubject;
use App\ConceptAssertionVisibility;
use App\Models\ActorProfile;
use App\Models\Concept;
use App\Models\ConceptAssertion;
use App\Models\User;
use App\ProfileIntentKind;
use App\ProfileIntentStatus;
use App\ProfileItemVisibility;

class SyncProfileIntentConceptAssertion
{
    private const PURPOSE = 'actor_profile_intent_summary';

    public function execute(
        User $user,
        ActorProfile $profile,
        Concept $concept,
        ProfileIntentKind $kind,
    ): void {
        $profile->loadMissing('actor');
        $predicate = $kind === ProfileIntentKind::Need
            ? ConceptAssertionPredicate::Needs
            : ConceptAssertionPredicate::Offers;

        $activeIntents = $profile->intents()
            ->where('concept_id', $concept->id)
            ->where('kind', $kind->value)
            ->where('status', ProfileIntentStatus::Active->value)
            ->get(['visibility']);

        $assertion = ConceptAssertion::query()
            ->where('subject_type', ConceptAssertionSubject::Actor->value)
            ->where('subject_id', $profile->actor_id)
            ->where('concept_id', $concept->id)
            ->where('predicate', $predicate->value)
            ->first();

        if ($activeIntents->isEmpty()) {
            if ($this->isManagedSummary($assertion)) {
                $assertion->delete();
            }

            return;
        }

        $visibility = $activeIntents->every(
            fn ($intent): bool => $intent->visibility === ProfileItemVisibility::Private,
        )
            ? ConceptAssertionVisibility::Private
            : ConceptAssertionVisibility::Inherited;

        if ($assertion instanceof ConceptAssertion) {
            if (! $this->isManagedSummary($assertion)) {
                return;
            }

            if ($assertion->visibility !== $visibility) {
                $assertion->visibility = $visibility;
                $assertion->save();
            }

            return;
        }

        app(AssertConcept::class)->execute(
            $user,
            $profile->actor,
            $concept,
            $predicate,
            visibility: $visibility,
            metadata: [
                'purpose' => self::PURPOSE,
                'actor_profile_id' => $profile->id,
            ],
        );
    }

    private function isManagedSummary(?ConceptAssertion $assertion): bool
    {
        return $assertion instanceof ConceptAssertion
            && ($assertion->metadata['purpose'] ?? null) === self::PURPOSE;
    }
}
