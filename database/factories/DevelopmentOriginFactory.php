<?php

namespace Database\Factories;

use App\Models\Actor;
use App\Models\DevelopmentOrigin;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<DevelopmentOrigin> */
class DevelopmentOriginFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'created_by_actor_id' => Actor::factory(),
            'supersedes_origin_id' => null,
            'source_type' => 'chatgpt',
            'source_url' => 'https://chatgpt.com/share/'.Str::uuid(),
            'title' => fake()->sentence(5),
            'summary' => fake()->paragraph(),
            'phase_key' => 'phase-07',
            'system_version' => 'development',
            'branch' => 'feat/example',
            'baseline_commit_sha' => str_repeat('a', 40),
            'result_commit_sha' => str_repeat('b', 40),
            'repository_paths' => ['docs/PROJECT_COMPASS.md', 'docs/PRODUCTION_ROADMAP.md'],
            'occurred_at' => now(),
        ];
    }
}
