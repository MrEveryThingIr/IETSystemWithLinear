<?php

namespace App\Support;

use App\Models\Asset;
use App\Models\SpaceContent;
use App\Models\SpaceContentDefinitionVersion;
use App\Models\SpaceContentRevision;
use App\Models\SpaceContentRevisionRelationship;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SpaceContentPublicationEvidence
{
    /**
     * @return Collection<int, array{asset_id: int, filename: string, code: string}>
     */
    public function issues(SpaceContentRevision $revision): Collection
    {
        $revision->loadMissing('assets');

        return $revision->assets
            ->flatMap(function (Asset $asset): array {
                $issues = [];

                if (! $asset->isPublishable()) {
                    $issues[] = $this->issue($asset, 'rights');
                }

                if ($asset->processing_status !== 'ready') {
                    $issues[] = $this->issue($asset, 'processing');
                }

                if ($this->scanEvidence($asset) === null) {
                    $issues[] = $this->issue($asset, 'scan');
                }

                if (! Asset::supportsMime($asset->mime_type)) {
                    $issues[] = $this->issue($asset, 'unsupported_type');
                }

                if (! Storage::disk($asset->disk)->exists($asset->storage_key)) {
                    $issues[] = $this->issue($asset, 'missing_file');
                }

                return $issues;
            })
            ->values();
    }

    public function seal(SpaceContentRevision $revision): string
    {
        $issues = $this->issues($revision);
        abort_if($issues->isNotEmpty(), 422, 'Resolve media publication checks before publishing.');

        $definitionVersion = SpaceContentDefinitionVersion::query()->findOrFail($revision->definition_version_id);
        $publishedAt = now();

        $this->sealAssets($revision, $publishedAt);
        $this->sealRelationships($revision, $publishedAt);

        $manifestHash = $this->manifestHash($revision, $definitionVersion);
        $revision->sealManifest($manifestHash);

        return $manifestHash;
    }

    private function sealAssets(SpaceContentRevision $revision, Carbon $publishedAt): void
    {
        $placements = DB::table('space_content_revision_assets')
            ->where('space_content_revision_id', $revision->id)
            ->orderBy('position')
            ->lockForUpdate()
            ->get();

        foreach ($placements as $placement) {
            $asset = Asset::query()->findOrFail($placement->asset_id);
            $scanEvidence = $this->scanEvidence($asset);
            abort_unless(is_string($scanEvidence), 422, 'Media scanning must be resolved before publishing.');

            $snapshot = [
                'asset_sha256_snapshot' => $asset->sha256,
                'rights_status_snapshot' => $asset->rights_status,
                'scan_status_snapshot' => $scanEvidence,
                'processing_status_snapshot' => $asset->processing_status,
                'source_attribution_snapshot' => $asset->source_attribution,
                'evidence_origin' => $scanEvidence === 'development_exempt' ? 'development_exempt' : 'publication',
                'published_evidence_at' => $publishedAt,
                'updated_at' => $publishedAt,
            ];

            $existingHasEvidence = $placement->published_evidence_at !== null;
            if ($existingHasEvidence) {
                abort_unless(
                    $placement->asset_sha256_snapshot === $snapshot['asset_sha256_snapshot']
                    && $placement->rights_status_snapshot === $snapshot['rights_status_snapshot']
                    && $placement->scan_status_snapshot === $snapshot['scan_status_snapshot']
                    && $placement->processing_status_snapshot === $snapshot['processing_status_snapshot']
                    && $placement->source_attribution_snapshot === $snapshot['source_attribution_snapshot'],
                    409,
                    'Published media evidence cannot be rewritten.',
                );

                continue;
            }

            DB::table('space_content_revision_assets')
                ->where('id', $placement->id)
                ->update($snapshot);
        }
    }

    private function sealRelationships(SpaceContentRevision $revision, Carbon $publishedAt): void
    {
        $parent = $revision->content()->firstOrFail();
        $relationships = DB::table('space_content_revision_relationships')
            ->where('parent_revision_id', $revision->id)
            ->where('relation_type', SpaceContentRevisionRelationship::TYPE_CONTAINS)
            ->orderBy('position')
            ->lockForUpdate()
            ->get();

        foreach ($relationships as $relationship) {
            $child = SpaceContent::query()->lockForUpdate()->findOrFail($relationship->child_content_id);
            abort_unless(
                (int) $child->group_space_id === (int) $parent->group_space_id,
                422,
                'Contained Content must belong to the same Space.',
            );
            abort_unless(
                $child->status === 'published' && $child->active_revision_id !== null,
                422,
                'Publish every contained child before publishing this parent edition.',
            );

            $childRevision = SpaceContentRevision::query()->lockForUpdate()->findOrFail($child->active_revision_id);
            abort_unless(
                is_string($childRevision->manifest_hash) && $childRevision->manifest_hash !== '',
                422,
                'Contained Content must have a sealed published edition before the parent can be published.',
            );

            if ($relationship->sealed_at !== null) {
                abort_unless(
                    (int) $relationship->child_revision_id === (int) $childRevision->id
                    && is_string($relationship->child_manifest_hash)
                    && hash_equals($relationship->child_manifest_hash, $childRevision->manifest_hash),
                    409,
                    'Published Content relationship evidence cannot be rewritten.',
                );

                continue;
            }

            DB::table('space_content_revision_relationships')
                ->where('id', $relationship->id)
                ->update([
                    'child_revision_id' => $childRevision->id,
                    'child_manifest_hash' => $childRevision->manifest_hash,
                    'sealed_at' => $publishedAt,
                    'updated_at' => $publishedAt,
                ]);
        }
    }

    private function manifestHash(
        SpaceContentRevision $revision,
        SpaceContentDefinitionVersion $definitionVersion,
    ): string {
        $placements = DB::table('space_content_revision_assets as placement')
            ->join('assets as asset', 'asset.id', '=', 'placement.asset_id')
            ->where('placement.space_content_revision_id', $revision->id)
            ->orderBy('placement.position')
            ->get([
                'asset.uuid as asset_uuid',
                'placement.role',
                'placement.position',
                'placement.caption',
                'placement.asset_sha256_snapshot',
                'placement.rights_status_snapshot',
                'placement.scan_status_snapshot',
                'placement.processing_status_snapshot',
                'placement.source_attribution_snapshot',
                'placement.evidence_origin',
            ])
            ->map(static fn (object $placement): array => [
                'asset_uuid' => $placement->asset_uuid,
                'sha256' => $placement->asset_sha256_snapshot,
                'role' => $placement->role,
                'position' => (int) $placement->position,
                'caption' => $placement->caption,
                'rights_status' => $placement->rights_status_snapshot,
                'scan_status' => $placement->scan_status_snapshot,
                'processing_status' => $placement->processing_status_snapshot,
                'source_attribution' => $placement->source_attribution_snapshot,
                'evidence_origin' => $placement->evidence_origin,
            ])
            ->all();

        $relationships = DB::table('space_content_revision_relationships')
            ->where('parent_revision_id', $revision->id)
            ->orderBy('relation_type')
            ->orderBy('position')
            ->get([
                'relation_type',
                'position',
                'child_content_id',
                'child_revision_id',
                'child_manifest_hash',
            ])
            ->map(static fn (object $relationship): array => [
                'type' => $relationship->relation_type,
                'position' => (int) $relationship->position,
                'child_content_id' => (int) $relationship->child_content_id,
                'child_revision_id' => (int) $relationship->child_revision_id,
                'child_manifest_hash' => $relationship->child_manifest_hash,
            ])
            ->all();

        return SpaceContentSchema::hashArray([
            'title' => trim($revision->title),
            'payload' => $revision->payload,
            'definition_version_hash' => $definitionVersion->content_hash,
            'blocks' => [],
            'assets' => $placements,
            'relationships' => $relationships,
        ]);
    }

    private function scanEvidence(Asset $asset): ?string
    {
        if ($asset->scan_status === 'clean') {
            return 'clean';
        }

        if (app()->environment(['local', 'testing']) && $asset->scan_status === 'unavailable') {
            return 'development_exempt';
        }

        return null;
    }

    /** @return array{asset_id: int, filename: string, code: string} */
    private function issue(Asset $asset, string $code): array
    {
        return [
            'asset_id' => $asset->id,
            'filename' => $asset->original_filename,
            'code' => $code,
        ];
    }
}
