<?php

namespace Database\Factories;

use App\Models\Actor;
use App\Models\SpaceContent;
use App\Models\SpaceContentDefinitionVersion;
use App\Models\SpaceContentRevision;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SpaceContentRevision> */
class SpaceContentRevisionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'space_content_id' => SpaceContent::factory(),
            'definition_version_id' => function (array $attributes): int {
                $content = SpaceContent::query()->findOrFail($attributes['space_content_id']);

                return SpaceContentDefinitionVersion::factory()->create([
                    'space_content_definition_id' => $content->space_content_definition_id,
                ])->id;
            },
            'revision' => 1,
            'title' => fake()->sentence(4),
            'payload' => ['summary' => fake()->sentence()],
            'created_by_actor_id' => Actor::factory(),
        ];
    }
}
