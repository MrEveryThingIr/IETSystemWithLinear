<?php

namespace Database\Factories;

use App\Models\Actor;
use App\Models\InteractionDefinition;
use App\Models\InteractionDefinitionVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InteractionDefinitionVersion> */
class InteractionDefinitionVersionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'interaction_definition_id' => InteractionDefinition::factory(),
            'version' => 1,
            'purpose_key' => 'questionnaire',
            'title' => fake()->sentence(4),
            'instructions' => fake()->sentence(),
            'items' => [[
                'key' => 'answer',
                'label' => 'Answer',
                'type' => 'long_text',
                'required' => true,
                'help' => null,
                'options' => [],
                'constraints' => ['max_length' => 10000],
            ]],
            'settings' => [
                'allow_withdrawal' => true,
                'max_attempts' => 1,
            ],
            'evaluation_config' => [
                'mode' => 'manual',
                'score_max' => null,
            ],
            'space_content_revision_id' => null,
            'created_by_actor_id' => Actor::factory(),
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->afterCreating(function (InteractionDefinitionVersion $version): void {
            $version->publish();

            $version->definition->applyLifecycle([
                'status' => InteractionDefinition::STATUS_ACTIVE,
                'current_version' => $version->version,
                'active_version_id' => $version->id,
                'draft_version_id' => null,
            ]);
        });
    }
}
