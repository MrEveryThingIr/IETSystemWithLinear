<?php

namespace App\Models;

use App\Support\SpaceContentSchema;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'uuid',
    'space_content_id',
    'definition_version_id',
    'revision',
    'title',
    'payload',
    'created_by_actor_id',
    'content_hash',
    'evidence_status',
    'manifest_hash',
    'manifest_version',
    'canonicalization_version',
    'manifest_algorithm',
    'canonical_manifest',
    'manifest_sealed_at',
])]
class SpaceContentRevision extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    public const EVIDENCE_UNSEALED = 'unsealed';
    public const EVIDENCE_SEALED = 'sealed';
    public const EVIDENCE_LEGACY_UNSEALED = 'legacy_unsealed';
    public const EVIDENCE_LEGACY_SEALED_V0 = 'legacy_sealed_v0';

    private bool $sealingManifest = false;

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'manifest_version' => 'integer',
            'canonicalization_version' => 'integer',
            'manifest_sealed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $revision): void {
            $revision->uuid ??= (string) Str::uuid();
            $content = SpaceContent::query()->findOrFail($revision->space_content_id);
            $version = SpaceContentDefinitionVersion::query()->findOrFail($revision->definition_version_id);
            if ((int) $version->space_content_definition_id !== (int) $content->space_content_definition_id) {
                throw new LogicException('Content Revision definition version must belong to the Content Definition.');
            }
            if ((int) $revision->revision < 1) {
                throw new LogicException('Content revision number must be positive.');
            }

            $revision->payload = SpaceContentSchema::normalizePayload($version->schema, $revision->payload ?? []);
            $revision->content_hash = SpaceContentSchema::hashRevision($revision->title, $revision->payload);
            $revision->evidence_status = self::EVIDENCE_UNSEALED;
            $revision->manifest_hash = null;
            $revision->manifest_version = null;
            $revision->canonicalization_version = null;
            $revision->manifest_algorithm = null;
            $revision->canonical_manifest = null;
            $revision->manifest_sealed_at = null;
        });

        static::updating(function (self $revision): void {
            if (! $revision->sealingManifest
                || $revision->isDirty([
                    'uuid',
                    'space_content_id',
                    'definition_version_id',
                    'revision',
                    'title',
                    'payload',
                    'created_by_actor_id',
                    'content_hash',
                ])) {
                throw new LogicException('Content revisions are immutable.');
            }

            if ($revision->getOriginal('manifest_hash') !== null
                || $revision->getOriginal('manifest_sealed_at') !== null) {
                throw new LogicException('Published Content manifests are immutable.');
            }

            if (! $revision->isDirty([
                'evidence_status',
                'manifest_hash',
                'manifest_version',
                'canonicalization_version',
                'manifest_algorithm',
                'canonical_manifest',
                'manifest_sealed_at',
            ])) {
                throw new LogicException('Content revisions are immutable.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Content revisions are immutable history.');
        });
    }

    public function sealManifest(
        string $hash,
        string $canonicalManifest,
        int $manifestVersion,
        int $canonicalizationVersion,
        string $algorithm = 'sha256',
    ): void {
        $hash = strtolower(trim($hash));
        $algorithm = strtolower(trim($algorithm));

        if (! preg_match('/^[a-f0-9]{64}$/', $hash)) {
            throw new LogicException('Content manifest hash must be a SHA-256 value.');
        }

        if ($manifestVersion < 1 || $canonicalizationVersion < 1 || $algorithm !== 'sha256') {
            throw new LogicException('Unsupported Content manifest contract.');
        }

        if ($this->manifest_hash !== null) {
            if (hash_equals($this->manifest_hash, $hash)) {
                return;
            }

            throw new LogicException('Published Content manifests cannot be resealed with different evidence.');
        }

        $this->sealingManifest = true;

        try {
            $this->update([
                'evidence_status' => self::EVIDENCE_SEALED,
                'manifest_hash' => $hash,
                'manifest_version' => $manifestVersion,
                'canonicalization_version' => $canonicalizationVersion,
                'manifest_algorithm' => $algorithm,
                'canonical_manifest' => $canonicalManifest,
                'manifest_sealed_at' => now(),
            ]);
        } finally {
            $this->sealingManifest = false;
        }
    }

    public function hasVerifiableManifest(): bool
    {
        return $this->evidence_status === self::EVIDENCE_SEALED
            && is_string($this->manifest_hash)
            && $this->manifest_hash !== ''
            && $this->manifest_version !== null
            && $this->canonicalization_version !== null
            && $this->manifest_algorithm === 'sha256'
            && is_string($this->canonical_manifest)
            && $this->canonical_manifest !== '';
    }

    /** @return BelongsTo<SpaceContent, $this> */
    public function content(): BelongsTo
    {
        return $this->belongsTo(SpaceContent::class, 'space_content_id');
    }

    /** @return BelongsTo<SpaceContentDefinitionVersion, $this> */
    public function definitionVersion(): BelongsTo
    {
        return $this->belongsTo(SpaceContentDefinitionVersion::class, 'definition_version_id');
    }

    /** @return BelongsTo<Actor, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'created_by_actor_id');
    }

    /** @return BelongsToMany<Asset, $this> */
    public function assets(): BelongsToMany
    {
        return $this->belongsToMany(
            Asset::class,
            'space_content_revision_assets',
            'space_content_revision_id',
            'asset_id',
        )
            ->withPivot([
                'uuid',
                'role',
                'position',
                'caption',
                'asset_sha256_snapshot',
                'rights_status_snapshot',
                'scan_status_snapshot',
                'processing_status_snapshot',
                'source_attribution_snapshot',
                'evidence_origin',
                'published_evidence_at',
            ])
            ->withTimestamps()
            ->orderByPivot('position');
    }

    /** @return HasMany<SpaceContentRevisionRelationship, $this> */
    public function relationships(): HasMany
    {
        return $this->hasMany(SpaceContentRevisionRelationship::class, 'parent_revision_id');
    }

    /** @return HasMany<SpaceContentRevisionRelationship, $this> */
    public function containedRelationships(): HasMany
    {
        return $this->relationships()
            ->where('relation_type', SpaceContentRevisionRelationship::TYPE_CONTAINS)
            ->orderBy('position');
    }

    /** @return HasMany<SpaceContentAnnotation, $this> */
    public function annotations(): HasMany
    {
        return $this->hasMany(SpaceContentAnnotation::class, 'space_content_revision_id');
    }

    /** @return HasMany<SpaceContentReaction, $this> */
    public function reactions(): HasMany
    {
        return $this->hasMany(SpaceContentReaction::class, 'space_content_revision_id');
    }
}
