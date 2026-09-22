<?php

namespace Database\Factories;

use App\Actions\Contexts\EnsureGroupSpaceContext;
use App\Models\Actor;
use App\Models\GroupSpace;
use App\Models\SpaceContentDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<SpaceContentDefinition> */
class SpaceContentDefinitionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'group_space_id' => GroupSpace::factory()->restricted(),
            'context_id' => function (array $attributes): int {
                $space = GroupSpace::query()->findOrFail($attributes['group_space_id']);

                return app(EnsureGroupSpaceContext::class)->execute($space)->id;
            },
            'created_by_actor_id' => Actor::factory(),
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'description' => fake()->optional()->sentence(),
            'status' => 'draft',
            'current_version' => 1,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => ['status' => 'active']);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => ['status' => 'archived']);
    }
}
