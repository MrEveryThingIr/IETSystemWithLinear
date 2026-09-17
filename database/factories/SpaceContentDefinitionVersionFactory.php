<?php

namespace Database\Factories;

use App\Models\Actor;
use App\Models\SpaceContentDefinition;
use App\Models\SpaceContentDefinitionVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SpaceContentDefinitionVersion> */
class SpaceContentDefinitionVersionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'space_content_definition_id' => SpaceContentDefinition::factory(),
            'version' => 1,
            'schema' => [
                'fields' => [[
                    'key' => 'summary',
                    'label' => 'Summary',
                    'type' => 'short_text',
                    'required' => true,
                    'help' => null,
                    'options' => [],
                ]],
            ],
            'display' => null,
            'created_by_actor_id' => Actor::factory(),
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => ['published_at' => now()]);
    }
}
