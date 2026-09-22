<?php

namespace Database\Factories;

use App\ContextKind;
use App\Models\ContentBlueprint;
use App\Models\ContentBlueprintVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ContentBlueprintVersion> */
class ContentBlueprintVersionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'content_blueprint_id' => ContentBlueprint::factory(),
            'version' => 1,
            'definition_schema' => [
                'fields' => [[
                    'key' => 'body',
                    'label' => 'Body',
                    'type' => 'long_text',
                    'required' => true,
                    'help' => null,
                    'options' => [],
                ]],
            ],
            'initial_blocks' => [[
                'type' => 'field',
                'data' => ['field_key' => 'body'],
                'style' => [],
            ]],
            'render_template_key' => 'article',
            'presentation' => [],
            'context_kinds' => array_map(
                static fn (ContextKind $kind): string => $kind->value,
                ContextKind::cases(),
            ),
            'concept_defaults' => [],
            'interaction_defaults' => [
                'annotations' => true,
                'reactions' => true,
                'default_annotation_visibility' => 'private',
            ],
            'authoring' => [
                'structured_fields' => true,
                'blocks' => true,
                'media' => true,
                'appearance' => true,
                'outline' => true,
            ],
            'created_by_actor_id' => null,
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->afterCreating(function (ContentBlueprintVersion $version): void {
            $version->publish();

            $version->blueprint->applyLifecycle([
                'status' => ContentBlueprint::STATUS_ACTIVE,
                'current_version' => $version->version,
                'active_version_id' => $version->id,
                'draft_version_id' => null,
            ]);
        });
    }
}
