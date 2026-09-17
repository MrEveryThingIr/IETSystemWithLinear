<?php

namespace Database\Factories;

use App\Models\Actor;
use App\Models\GroupSpace;
use App\Models\SpaceContent;
use App\Models\SpaceContentDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SpaceContent> */
class SpaceContentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'group_space_id' => GroupSpace::factory()->restricted(),
            'space_content_definition_id' => function (array $attributes): int {
                return SpaceContentDefinition::factory()->create([
                    'group_space_id' => $attributes['group_space_id'],
                ])->id;
            },
            'author_actor_id' => Actor::factory(),
            'status' => 'draft',
            'current_revision' => 1,
            'published_at' => null,
            'archived_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => 'published',
            'published_at' => now(),
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => [
            'status' => 'archived',
            'archived_at' => now(),
        ]);
    }
}
