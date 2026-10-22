<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['actor_profile_intent_id', 'asset_id', 'position', 'caption', 'created_by_actor_id'])]
class ActorProfileIntentMedia extends Model
{
    public const MAX_PER_INTENT = 6;

    protected $table = 'actor_profile_intent_media';

    protected static function booted(): void
    {
        static::creating(function (self $media): void {
            $media->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<ActorProfileIntent, $this> */
    public function intent(): BelongsTo
    {
        return $this->belongsTo(ActorProfileIntent::class, 'actor_profile_intent_id');
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
