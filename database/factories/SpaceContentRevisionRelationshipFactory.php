<?php

namespace Database\Factories;

use App\Models\SpaceContent;
use App\Models\SpaceContentRevision;
use App\Models\SpaceContentRevisionRelationship;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SpaceContentRevisionRelationship> */
class SpaceContentRevisionRelationshipFactory extends Factory
{
    protected $model = SpaceContentRevisionRelationship::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'parent_revision_id' => SpaceContentRevision::factory(),
            'child_content_id' => function (array $attributes): int {
                $parent = SpaceContentRevision::query()->with('content')->findOrFail($attributes['parent_revision_id']);

                return SpaceContent::factory()->create([
                    'group_space_id' => $parent->content->group_space_id,
                ])->id;
            },
            'relation_type' => SpaceContentRevisionRelationship::TYPE_CONTAINS,
            'position' => 0,
            'child_revision_id' => null,
            'child_manifest_hash' => null,
            'sealed_at' => null,
        ];
    }
}
