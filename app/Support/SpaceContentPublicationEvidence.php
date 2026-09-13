<?php

namespace App\Support;

use App\Models\Asset;
use App\Models\SpaceContentDefinitionVersion;
use App\Models\SpaceContentRevision;
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

        $manifestHash = $this->manifestHash($revision, $definitionVersion);
        $revision->sealManifest($manifestHash);

        return $manifestHash;
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

        return SpaceContentSchema::hashArray([
            'title' => trim($revision->title),
            'payload' => $revision->payload,
            'definition_version_hash' => $definitionVersion->content_hash,
            'blocks' => [],
            'assets' => $placements,
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
