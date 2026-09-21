<?php

namespace Database\Factories;

use App\Models\Actor;
use App\Models\ConceptScheme;
use App\Models\ConceptVocabulary;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ConceptScheme> */
class ConceptSchemeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'vocabulary_id' => ConceptVocabulary::factory(),
            'name' => fake()->unique()->words(2, true),
            'slug' => fake()->unique()->slug(2),
            'description' => fake()->sentence(),
            'created_by_actor_id' => Actor::factory(),
            'metadata' => [],
        ];
    }
}
