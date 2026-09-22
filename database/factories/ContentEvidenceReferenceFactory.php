<?php

namespace Database\Factories;

use App\ContentEvidenceTarget;
use App\Models\Actor;
use App\Models\ContentEvidenceReference;
use App\Models\Context;
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
            'context_id' => Context::factory(),
            'space_content_id' => SpaceContent::factory(),
            'space_content_revision_id' => SpaceContentRevision::factory(),
            'target_type' => ContentEvidenceTarget::Revision,
            'target_uuid' => null,
            'field_key' => null,
            'created_by_actor_id' => Actor::factory(),
            'metadata' => [],
        ];
    }
}
