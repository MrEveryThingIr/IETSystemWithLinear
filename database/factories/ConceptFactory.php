<?php

namespace Database\Factories;

use App\Models\Concept;
use App\Models\ConceptVocabulary;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Concept> */
class ConceptFactory extends Factory
{
    public function definition(): array
    {
        return [
            'vocabulary_id' => ConceptVocabulary::factory(),
            'slug' => fake()->unique()->slug(2),
            'summary' => fake()->sentence(),
            'metadata' => [],
        ];
    }
}
