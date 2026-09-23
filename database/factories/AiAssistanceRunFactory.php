<?php

namespace Database\Factories;

use App\Models\Actor;
use App\Models\AiAssistanceRun;
use App\Models\SpaceContent;
use App\Models\SpaceContentRevision;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<AiAssistanceRun> */
class AiAssistanceRunFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'context_id' => fn (array $attributes): int => SpaceContent::query()->findOrFail($attributes['space_content_id'])->context_id,
            'space_content_id' => SpaceContent::factory(),
            'base_revision_id' => function (array $attributes): int {
                return SpaceContentRevision::factory()->create([
                    'space_content_id' => $attributes['space_content_id'],
                ])->id;
            },
            'requested_by_actor_id' => Actor::factory(),
            'status' => AiAssistanceRun::STATUS_PLANNED,
            'provider' => 'openai',
            'model' => 'gpt-test',
            'external_response_id' => 'resp_test',
            'prompt' => 'Improve this content.',
            'request_hash' => hash('sha256', 'test'),
            'proposal' => [
                'summary' => 'Test proposal.',
                'apply_title' => false,
                'title' => '',
                'field_updates' => [],
                'block_operations' => [],
                'apply_presentation' => false,
                'presentation' => [],
                'media_requests' => [],
            ],
            'error' => null,
            'planned_at' => now(),
            'applied_revision_id' => null,
            'applied_at' => null,
        ];
    }
}
