<?php

namespace Database\Factories;

use App\ConceptAssertionPredicate;
use App\ConceptAssertionSource;
use App\ConceptAssertionSubject;
use App\ConceptAssertionVisibility;
use App\Models\Actor;
use App\Models\Concept;
use App\Models\ConceptAssertion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ConceptAssertion> */
class ConceptAssertionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'subject_type' => ConceptAssertionSubject::Actor,
            'subject_id' => Actor::factory(),
            'concept_id' => Concept::factory(),
            'predicate' => ConceptAssertionPredicate::InterestedIn,
            'source' => ConceptAssertionSource::Manual,
            'visibility' => ConceptAssertionVisibility::Private,
            'created_by_actor_id' => Actor::factory(),
            'metadata' => [],
        ];
    }
}
