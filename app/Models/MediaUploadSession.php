<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A resumable upload in progress. Bytes are appended chunk by chunk to a
 * private temporary file; on completion the file becomes an ordinary Asset
 * through the existing attach action.
 */
#[Fillable([
    'uuid',
    'context_id',
    'space_content_id',
    'actor_id',
    'original_filename',
    'mime_type',
    'byte_size',
    'chunk_size',
    'received_bytes',
    'status',
    'temp_key',
    'rights_status',
    'caption',
    'asset_id',
    'error',
    'expires_at',
    'completed_at',
])]
class MediaUploadSession extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_COMPLETING = 'completing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_EXPIRED = 'expired';

    protected function casts(): array
    {
        return [
            'byte_size' => 'integer',
            'chunk_size' => 'integer',
            'received_bytes' => 'integer',
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $session): void {
            $session->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN && $this->expires_at->isFuture();
    }

    /** @return BelongsTo<Context, $this> */
    public function context(): BelongsTo
    {
        return $this->belongsTo(Context::class);
    }

    /** @return BelongsTo<SpaceContent, $this> */
    public function content(): BelongsTo
    {
        return $this->belongsTo(SpaceContent::class, 'space_content_id');
    }

    /** @return BelongsTo<Actor, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(Actor::class);
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
