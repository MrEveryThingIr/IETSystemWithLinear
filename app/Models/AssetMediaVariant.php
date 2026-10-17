<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A derivative of an immutable Asset (poster frame, original metadata, and
 * later streaming renditions). The original Asset is never overwritten.
 */
#[Fillable([
    'uuid',
    'asset_id',
    'type',
    'profile',
    'width',
    'height',
    'duration_ms',
    'bitrate',
    'codec',
    'container',
    'mime_type',
    'disk',
    'storage_key',
    'provider_key',
    'byte_size',
    'processing_status',
])]
class AssetMediaVariant extends Model
{
    public const TYPE_ORIGINAL = 'original';

    public const TYPE_POSTER = 'poster';

    public const TYPE_HLS = 'hls';

    public const TYPE_MP4 = 'mp4';

    /** @var list<string> */
    public const TYPES = [self::TYPE_ORIGINAL, self::TYPE_POSTER, self::TYPE_HLS, self::TYPE_MP4];

    protected function casts(): array
    {
        return [
            'width' => 'integer',
            'height' => 'integer',
            'duration_ms' => 'integer',
            'bitrate' => 'integer',
            'byte_size' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $variant): void {
            $variant->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
