<?php

namespace App\Support;

use App\ConceptAssertionSubject;
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
    public const MANIFEST_VERSION = 3;
    public const CANONICALIZATION_VERSION = 1;
    public const ALGORITHM = 'sha256';

    /**
     * @return Collection<int, array{asset_id: int, filename: string, code: string}>
     */
    public function issues(SpaceContentRevision $revision, bool $verifyStoredFile = false): Collection
    {
        $revision->loadMissing('assets');

        return $revision->assets
            ->flatMap(function (Asset $asset) use ($verifyStoredFile): array {
                $issues = [];

                if (! $asset->isPublishable()) {
                    $issues[] = $this->issue($asset, 'rights');
                }

                if ($asset->processing_status !== 'ready' || $asset->readiness_verified_at === null) {
                    $issues[] = $this->issue($asset, 'processing');
                }

                if ($this->scanEvidence($asset) === null) {
                    $issues[] = $this->issue($asset, 'scan');
                }

                if (! Asset::supportsMime($asset->mime_type)) {
                    $issues[] = $this->issue($asset, 'unsupported_type');
                }

                if ($verifyStoredFile && ! $this->storedIdentityMatches($asset)) {
                    $issues[] = $this->issue($asset, 'missing_file');
                }

                return $issues;
            })
            ->values();
    }

    public function seal(SpaceContentRevision $revision): string
    {
        $issues = $this->issues($revision, true);
        abort_if($issues->isNotEmpty(), 422, 'Resolve media publication checks before publishing.');

        $definitionVersion = SpaceContentDefinitionVersion::query()->findOrFail($revision->definition_version_id);
        $publishedAt = now();

        $this->sealAssets($revision, $publishedAt);
        $this->sealRelationships($revision, $publishedAt);

        $manifest = $this->manifest($revision, $definitionVersion);
        $canonicalManifest = SpaceContentSchema::canonicalJson($manifest);
        $manifestHash = hash(self::ALGORITHM, $canonicalManifest);

        $revision->sealManifest(
            $manifestHash,
            $canonicalManifest,
            self::MANIFEST_VERSION,
            self::CANONICALIZATION_VERSION,
            self::ALGORITHM,
        );

        return $manifestHash;
    }

    private function sealAssets(SpaceContentRevision $revision, Carbon $publishedAt): void
    {
        $placements = DB::table('space_content_revision_assets')
            ->where('space_content_revision_id', $revision->id)
            ->orderBy('position')
            ->lockForUpdate()
            ->get();

        if ($placements->isEmpty()) {
            return;
        }

        $assets = Asset::query()
            ->whereIn('id', $placements->pluck('asset_id')->all())
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($placements as $placement) {
            $asset = $assets->get((int) $placement->asset_id);
            abort_unless($asset instanceof Asset, 422, 'Referenced media no longer exists.');
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

            if ($placement->published_evidence_at !== null) {
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

        if ($relationships->isEmpty()) {
            return;
        }

        $children = SpaceContent::query()
            ->whereIn('id', $relationships->pluck('child_content_id')->all())
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $revisionIds = $children->pluck('active_revision_id')->filter()->all();
        $childRevisions = SpaceContentRevision::query()
            ->whereIn('id', $revisionIds)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($relationships as $relationship) {
            $child = $children->get((int) $relationship->child_content_id);
            abort_unless($child instanceof SpaceContent, 422, 'Contained Content no longer exists.');
            abort_unless((int) $child->group_space_id === (int) $parent->group_space_id, 422, 'Contained Content must belong to the same Space.');
            abort_unless($child->status === 'published' && $child->active_revision_id !== null, 422, 'Publish every contained child before publishing this parent edition.');

            $childRevision = $childRevisions->get((int) $child->active_revision_id);
            abort_unless($childRevision instanceof SpaceContentRevision && $childRevision->hasVerifiableManifest(), 422, 'Contained Content must have a verifiable sealed edition before the parent can be published.');

            if ($relationship->sealed_at !== null) {
                abort_unless(
                    (int) $relationship->child_revision_id === (int) $childRevision->id
                    && is_string($relationship->child_manifest_hash)
                    && hash_equals($relationship->child_manifest_hash, (string) $childRevision->manifest_hash),
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

    /** @return array<string, mixed> */
    private function manifest(SpaceContentRevision $revision, SpaceContentDefinitionVersion $definitionVersion): array
    {
        $revision->loadMissing('content');

        $placements = DB::table('space_content_revision_assets as placement')
            ->join('assets as asset', 'asset.id', '=', 'placement.asset_id')
            ->where('placement.space_content_revision_id', $revision->id)
            ->orderBy('placement.position')
            ->get([
                'placement.uuid as placement_uuid',
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
                'placement_uuid' => $placement->placement_uuid,
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

        $blockRows = DB::table('space_content_blocks')
            ->where('space_content_revision_id', $revision->id)
            ->orderBy('parent_block_id')
            ->orderBy('position')
            ->orderBy('id')
            ->get();
        $blockUuidById = [];
        foreach ($blockRows as $block) {
            $blockUuidById[(int) $block->id] = (string) $block->uuid;
        }
        $blocks = [];
        foreach ($blockRows as $block) {
            $data = json_decode((string) $block->data, true);
            $style = $block->style !== null ? json_decode((string) $block->style, true) : null;
            $blocks[] = [
                'block_uuid' => (string) $block->uuid,
                'logical_uuid' => (string) $block->logical_uuid,
                'parent_block_uuid' => $block->parent_block_id !== null
                    ? ($blockUuidById[(int) $block->parent_block_id] ?? null)
                    : null,
                'type' => (string) $block->type,
                'position' => (int) $block->position,
                'data' => is_array($data) ? $data : [],
                'style' => is_array($style) ? $style : [],
            ];
        }

        $relationships = DB::table('space_content_revision_relationships as relationship')
            ->join('space_contents as child_content', 'child_content.id', '=', 'relationship.child_content_id')
            ->join('space_content_revisions as child_revision', 'child_revision.id', '=', 'relationship.child_revision_id')
            ->where('relationship.parent_revision_id', $revision->id)
            ->orderBy('relationship.relation_type')
            ->orderBy('relationship.position')
            ->get([
                'relationship.uuid as relationship_uuid',
                'relationship.relation_type',
                'relationship.position',
                'child_content.uuid as child_content_uuid',
                'child_revision.uuid as child_revision_uuid',
                'relationship.child_manifest_hash',
            ])
            ->map(static fn (object $relationship): array => [
                'relationship_uuid' => $relationship->relationship_uuid,
                'type' => $relationship->relation_type,
                'position' => (int) $relationship->position,
                'child_content_uuid' => $relationship->child_content_uuid,
                'child_revision_uuid' => $relationship->child_revision_uuid,
                'child_manifest_hash' => $relationship->child_manifest_hash,
            ])
            ->all();

        $semanticAssertions = DB::table('concept_assertions as assertion')
            ->join('concepts as concept', 'concept.id', '=', 'assertion.concept_id')
            ->leftJoin('concept_schemes as scheme', 'scheme.id', '=', 'assertion.scheme_id')
            ->where('assertion.subject_type', ConceptAssertionSubject::SpaceContentRevision->value)
            ->where('assertion.subject_id', $revision->id)
            ->orderBy('assertion.predicate')
            ->orderBy('concept.uuid')
            ->orderBy('assertion.uuid')
            ->get([
                'assertion.uuid as assertion_uuid',
                'assertion.predicate',
                'concept.uuid as concept_uuid',
                'scheme.uuid as scheme_uuid',
                'assertion.weight',
                'assertion.confidence',
                'assertion.source',
                'assertion.visibility',
                'assertion.valid_from',
                'assertion.valid_until',
                'assertion.metadata',
            ])
            ->map(fn (object $assertion): array => [
                'assertion_uuid' => (string) $assertion->assertion_uuid,
                'predicate' => (string) $assertion->predicate,
                'concept_uuid' => (string) $assertion->concept_uuid,
                'scheme_uuid' => $assertion->scheme_uuid !== null ? (string) $assertion->scheme_uuid : null,
                'weight' => $this->canonicalDecimal($assertion->weight),
                'confidence' => $this->canonicalDecimal($assertion->confidence),
                'source' => (string) $assertion->source,
                'visibility' => (string) $assertion->visibility,
                'valid_from' => $this->canonicalTimestamp($assertion->valid_from),
                'valid_until' => $this->canonicalTimestamp($assertion->valid_until),
                'metadata' => $this->canonicalMetadata($assertion->metadata),
            ])
            ->all();

        return [
            'manifest_version' => self::MANIFEST_VERSION,
            'canonicalization_version' => self::CANONICALIZATION_VERSION,
            'algorithm' => self::ALGORITHM,
            'content_uuid' => $revision->content->uuid,
            'revision_uuid' => $revision->uuid,
            'title' => trim($revision->title),
            'payload' => $revision->payload,
            'definition_version_hash' => $definitionVersion->content_hash,
            'composition_mode' => $revision->composition_mode,
            'render_template_key' => $revision->render_template_key,
            'render_template_uuid' => $revision->render_template_uuid,
            'presentation' => $revision->presentation,
            'blocks' => $blocks,
            'assets' => $placements,
            'relationships' => $relationships,
            'semantic_assertions' => $semanticAssertions,
        ];
    }

    private function canonicalDecimal(mixed $value): ?string
    {
        return $value === null
            ? null
            : number_format((float) $value, 4, '.', '');
    }

    private function canonicalTimestamp(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return Carbon::parse((string) $value)
            ->utc()
            ->format('Y-m-d\\TH:i:s.u\\Z');
    }

    /** @return array<string, mixed> */
    private function canonicalMetadata(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function storedIdentityMatches(Asset $asset): bool
    {
        if (! Storage::disk($asset->disk)->exists($asset->storage_key)) {
            return false;
        }

        $stream = Storage::disk($asset->disk)->readStream($asset->storage_key);
        if (! is_resource($stream)) {
            return false;
        }

        $context = hash_init('sha256');
        try {
            hash_update_stream($context, $stream);
        } finally {
            fclose($stream);
        }

        return hash_equals($asset->sha256, hash_final($context));
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
