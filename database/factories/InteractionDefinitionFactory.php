<?php

namespace Database\Factories;

use App\Models\Actor;
use App\Models\Context;
use App\Models\InteractionDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<InteractionDefinition> */
class InteractionDefinitionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'context_id' => Context::factory(),
            'space_content_id' => null,
            'name' => fake()->sentence(3),
            'status' => InteractionDefinition::STATUS_DRAFT,
            'current_version' => 1,
            'active_version_id' => null,
            'draft_version_id' => null,
            'created_by_actor_id' => Actor::factory(),
        ];
    }
}
