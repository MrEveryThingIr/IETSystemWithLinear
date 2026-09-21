<?php

namespace Database\Factories;

use App\ConceptAssertionSource;
use App\Models\Actor;
use App\Models\Concept;
use App\Models\ConceptRelation;
use App\Models\ConceptRelationType;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ConceptRelation> */
class ConceptRelationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'from_concept_id' => Concept::factory(),
            'relation_type_id' => fn (): int => ConceptRelationType::query()
                ->where('key', 'related_to')
                ->firstOrFail()
                ->id,
            'to_concept_id' => Concept::factory(),
            'source' => ConceptAssertionSource::Manual,
            'created_by_actor_id' => Actor::factory(),
            'metadata' => [],
        ];
    }
}
