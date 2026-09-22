<?php

namespace Database\Factories;

use App\Models\Actor;
use App\Models\ContentBlueprint;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ContentBlueprint> */
class ContentBlueprintFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'uuid' => (string) Str::uuid(),
            'slug' => Str::slug($name).'-'.fake()->unique()->randomNumber(6),
            'name' => Str::title($name),
            'description' => fake()->sentence(),
            'category' => 'general',
            'scope' => ContentBlueprint::SCOPE_ACTOR,
            'owner_actor_id' => Actor::factory(),
            'context_id' => null,
            'status' => ContentBlueprint::STATUS_ACTIVE,
            'current_version' => 1,
            'active_version_id' => null,
            'draft_version_id' => null,
            'cloned_from_version_id' => null,
        ];
    }
}
