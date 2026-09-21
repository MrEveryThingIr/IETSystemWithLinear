<?php

namespace Database\Factories;

use App\ConceptVocabularyScope;
use App\Models\Actor;
use App\Models\ConceptVocabulary;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ConceptVocabulary> */
class ConceptVocabularyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'scope_type' => ConceptVocabularyScope::Platform,
            'scope_id' => 0,
            'name' => fake()->unique()->words(2, true),
            'slug' => fake()->unique()->slug(2),
            'created_by_actor_id' => Actor::factory(),
            'metadata' => [],
        ];
    }

    public function forActor(Actor $actor): static
    {
        return $this->state(fn (): array => [
            'scope_type' => ConceptVocabularyScope::Actor,
            'scope_id' => $actor->id,
            'created_by_actor_id' => $actor->id,
        ]);
    }
}
