<?php

namespace App\Support;

use App\Models\SpaceContentRevision;
use Illuminate\Support\Facades\DB;

class SpaceContentRevisionComposition
{
    /** @param list<int> $excludedAssetIds */
    public function copyAssets(
        SpaceContentRevision $source,
        SpaceContentRevision $target,
        array $excludedAssetIds = [],
    ): void {
        $placements = DB::table('space_content_revision_assets')
            ->where('space_content_revision_id', $source->id)
            ->when(
                $excludedAssetIds !== [],
                fn ($query) => $query->whereNotIn('asset_id', $excludedAssetIds),
            )
            ->orderBy('position')
            ->get();

        foreach ($placements->values() as $position => $placement) {
            DB::table('space_content_revision_assets')->insert([
                'space_content_revision_id' => $target->id,
                'asset_id' => $placement->asset_id,
                'role' => $placement->role,
                'position' => $position,
                'caption' => $placement->caption,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function copyRelationships(
        SpaceContentRevision $source,
        SpaceContentRevision $target,
        ?string $exceptType = null,
    ): void {
        $relationships = DB::table('space_content_revision_relationships')
            ->where('parent_revision_id', $source->id)
            ->when(
                $exceptType !== null,
                fn ($query) => $query->where('relation_type', '!=', $exceptType),
            )
            ->orderBy('relation_type')
            ->orderBy('position')
            ->get();

        foreach ($relationships as $relationship) {
            DB::table('space_content_revision_relationships')->insert([
                'parent_revision_id' => $target->id,
                'child_content_id' => $relationship->child_content_id,
                'relation_type' => $relationship->relation_type,
                'position' => $relationship->position,
                'child_revision_id' => null,
                'child_manifest_hash' => null,
                'sealed_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
