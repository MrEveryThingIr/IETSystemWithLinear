<?php

namespace Database\Factories;

use App\Models\Actor;
use App\Models\ContentPlacement;
use App\Models\Context;
use App\Models\SpaceContent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ContentPlacement> */
class ContentPlacementFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'context_id' => Context::factory(),
            'space_content_id' => SpaceContent::factory(),
            'placed_by_actor_id' => Actor::factory(),
            'status' => ContentPlacement::STATUS_ACTIVE,
            'removed_at' => null,
            'removed_by_actor_id' => null,
        ];
    }

    public function removed(): static
    {
        return $this->state(fn (): array => [
            'status' => ContentPlacement::STATUS_REMOVED,
            'removed_at' => now(),
            'removed_by_actor_id' => Actor::factory(),
        ]);
    }
}
