<?php

namespace Database\Factories;

use App\ContextKind;
use App\Models\Context;
use App\Models\Relationship;
use App\Models\RelationshipContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<RelationshipContext> */
class RelationshipContextFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'context_id' => Context::factory()->state(['kind' => ContextKind::Relationship]),
            'relationship_id' => Relationship::factory(),
        ];
    }
}
