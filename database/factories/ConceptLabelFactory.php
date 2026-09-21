<?php

namespace Database\Factories;

use App\ConceptLabelKind;
use App\Models\Concept;
use App\Models\ConceptLabel;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ConceptLabel> */
class ConceptLabelFactory extends Factory
{
    public function definition(): array
    {
        $label = fake()->unique()->words(2, true);

        return [
            'concept_id' => Concept::factory(),
            'locale' => 'en',
            'label' => $label,
            'kind' => ConceptLabelKind::Preferred,
            'normalized_label' => mb_strtolower($label),
        ];
    }
}
