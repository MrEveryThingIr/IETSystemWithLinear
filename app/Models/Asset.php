<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'uuid',
    'group_space_id',
    'original_filename',
    'mime_type',
    'extension',
    'byte_size',
    'disk',
    'storage_key',
    'sha256',
    'uploaded_by_actor_id',
    'scan_status',
    'processing_status',
    'rights_status',
    'source_attribution',
    'alt_text',
    'metadata',
])]
class Asset extends Model
{
    use HasFactory;

    /** @var list<string> */
    public const RIGHTS_STATUSES = [
        'owned',
        'licensed',
        'public_domain',
        'permission_granted',
        'private_study_only',
        'unknown',
    ];

    /** @var list<string> */
    public const PUBLISHABLE_RIGHTS_STATUSES = [
        'owned',
        'licensed',
        'public_domain',
        'permission_granted',
    ];

    /** @var list<string> */
    private const SUPPORTED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/avif',
        'audio/mpeg',
        'audio/mp4',
        'audio/aac',
        'audio/wav',
        'audio/x-wav',
        'audio/ogg',
        'audio/webm',
        'audio/flac',
        'video/mp4',
        'video/webm',
        'video/quicktime',
        'application/pdf',
        'text/plain',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'byte_size' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $asset): void {
            $asset->uuid ??= (string) Str::uuid();

            if (! in_array($asset->rights_status, self::RIGHTS_STATUSES, true)) {
                throw new LogicException('Unknown Asset rights status.');
            }

            if (! self::supportsMime($asset->mime_type)) {
                throw new LogicException('Unsupported Asset media type.');
            }
        });

        static::updating(function (self $asset): void {
            if ($asset->isDirty([
                'uuid',
                'group_space_id',
                'original_filename',
                'mime_type',
                'extension',
                'byte_size',
                'disk',
                'storage_key',
                'sha256',
                'uploaded_by_actor_id',
            ])) {
                throw new LogicException('Asset provenance and stored file identity are immutable.');
            }

            if ($asset->isDirty('rights_status') && ! in_array($asset->rights_status, self::RIGHTS_STATUSES, true)) {
                throw new LogicException('Unknown Asset rights status.');
            }
        });

        static::deleting(function (self $asset): void {
            if ($asset->revisions()->exists()) {
                throw new LogicException('Referenced Assets are preserved with Content history.');
            }
        });
    }

    public static function supportsMime(string $mime): bool
    {
        return in_array($mime, self::SUPPORTED_MIME_TYPES, true);
    }

    public function isPublishable(): bool
    {
        return in_array($this->rights_status, self::PUBLISHABLE_RIGHTS_STATUSES, true);
    }

    public function mediaKind(): string
    {
        if (str_starts_with($this->mime_type, 'image/')) {
            return 'image';
        }

        if (str_starts_with($this->mime_type, 'audio/')) {
            return 'audio';
        }

        if (str_starts_with($this->mime_type, 'video/')) {
            return 'video';
        }

        return $this->mime_type === 'application/pdf' ? 'pdf' : 'file';
    }

    /** @return BelongsTo<GroupSpace, $this> */
    public function space(): BelongsTo
    {
        return $this->belongsTo(GroupSpace::class, 'group_space_id');
    }

    /** @return BelongsTo<Actor, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'uploaded_by_actor_id');
    }

    /** @return BelongsToMany<SpaceContentRevision, $this> */
    public function revisions(): BelongsToMany
    {
        return $this->belongsToMany(
            SpaceContentRevision::class,
            'space_content_revision_assets',
            'asset_id',
            'space_content_revision_id',
        )->withPivot(['role', 'position', 'caption'])->withTimestamps();
    }
}
