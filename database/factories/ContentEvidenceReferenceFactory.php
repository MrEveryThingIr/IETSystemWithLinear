<?php

namespace Database\Factories;

use App\ContentEvidenceTarget;
use App\Models\Actor;
use App\Models\ContentEvidenceReference;
use App\Models\SpaceContent;
use App\Models\SpaceContentRevision;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ContentEvidenceReference> */
class ContentEvidenceReferenceFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'space_content_id' => SpaceContent::factory(),
            'context_id' => function (array $attributes): int {
                return SpaceContent::query()->findOrFail($attributes['space_content_id'])->context_id;
            },
            'space_content_revision_id' => function (array $attributes): int {
                return SpaceContentRevision::factory()->create([
                    'space_content_id' => $attributes['space_content_id'],
                ])->id;
            },
            'target_type' => ContentEvidenceTarget::Revision,
            'target_uuid' => null,
            'field_key' => null,
            'created_by_actor_id' => Actor::factory(),
            'metadata' => [],
        ];
    }
}
