<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** A WebVTT captions, subtitles or transcript track for an audio/video Asset. */
#[Fillable([
    'uuid',
    'asset_id',
    'kind',
    'language',
    'label',
    'disk',
    'storage_key',
    'byte_size',
    'is_default',
    'created_by_actor_id',
])]
class AssetMediaTrack extends Model
{
    /** @var list<string> */
    public const KINDS = ['captions', 'subtitles'];

    protected function casts(): array
    {
        return [
            'byte_size' => 'integer',
            'is_default' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $track): void {
            $track->uuid ??= (string) Str::uuid();
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

    /** @return BelongsTo<Actor, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'created_by_actor_id');
    }
}
