<?php

namespace App\Support;

use App\Models\SpaceContentBlock;
use App\Models\SpaceContentRevision;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SpaceContentRevisionComposition
{
    /**
     * @param list<int> $excludedAssetIds
     * @return array<string, string> old placement UUID => new placement UUID
     */
    public function copyAssets(
        SpaceContentRevision $source,
        SpaceContentRevision $target,
        array $excludedAssetIds = [],
    ): array {
        $now = now();
        $placements = DB::table('space_content_revision_assets')
            ->where('space_content_revision_id', $source->id)
            ->when($excludedAssetIds !== [], fn ($query) => $query->whereNotIn('asset_id', $excludedAssetIds))
            ->orderBy('position')
            ->get()
            ->values();

        $rows = [];
        $placementMap = [];
        foreach ($placements as $position => $placement) {
            $newUuid = (string) Str::uuid();
            $placementMap[(string) $placement->uuid] = $newUuid;
            $rows[] = [
                'uuid' => $newUuid,
                'space_content_revision_id' => $target->id,
                'asset_id' => $placement->asset_id,
                'role' => $placement->role,
                'position' => $position,
                'caption' => $placement->caption,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            DB::table('space_content_revision_assets')->insert($rows);
        }

        return $placementMap;
    }

    /** @param array<string, string> $placementMap */
    public function copyBlocks(
        SpaceContentRevision $source,
        SpaceContentRevision $target,
        array $placementMap = [],
    ): void {
        $blocks = DB::table('space_content_blocks')
            ->where('space_content_revision_id', $source->id)
            ->orderBy('id')
            ->get();

        if ($blocks->isEmpty()) {
            return;
        }

        $idMap = [];
        foreach ($blocks as $block) {
            $data = json_decode((string) $block->data, true);
            $data = is_array($data) ? $data : [];

            if (isset($data['asset_placement_uuid']) && is_string($data['asset_placement_uuid'])) {
                $mapped = $placementMap[$data['asset_placement_uuid']] ?? null;
                if (! is_string($mapped)) {
                    continue;
                }
                $data['asset_placement_uuid'] = $mapped;
            }

            $style = $block->style !== null ? json_decode((string) $block->style, true) : null;
            $parentId = $block->parent_block_id !== null ? ($idMap[(int) $block->parent_block_id] ?? null) : null;
            if ($block->parent_block_id !== null && $parentId === null) {
                continue;
            }

            $newId = DB::table('space_content_blocks')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'logical_uuid' => $block->logical_uuid,
                'space_content_revision_id' => $target->id,
                'parent_block_id' => $parentId,
                'type' => $block->type,
                'position' => $block->position,
                'data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'style' => is_array($style) ? json_encode($style, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $idMap[(int) $block->id] = $newId;
        }
    }

    public function copyRelationships(
        SpaceContentRevision $source,
        SpaceContentRevision $target,
        ?string $exceptType = null,
    ): void {
        $now = now();
        $rows = DB::table('space_content_revision_relationships')
            ->where('parent_revision_id', $source->id)
            ->when($exceptType !== null, fn ($query) => $query->where('relation_type', '!=', $exceptType))
            ->orderBy('relation_type')
            ->orderBy('position')
            ->get()
            ->map(static fn (object $relationship): array => [
                'uuid' => (string) Str::uuid(),
                'parent_revision_id' => $target->id,
                'child_content_id' => $relationship->child_content_id,
                'relation_type' => $relationship->relation_type,
                'position' => $relationship->position,
                'child_revision_id' => null,
                'child_manifest_hash' => null,
                'sealed_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        if ($rows !== []) {
            DB::table('space_content_revision_relationships')->insert($rows);
        }
    }
}
