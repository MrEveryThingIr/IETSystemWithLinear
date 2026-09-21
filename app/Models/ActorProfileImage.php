<?php

namespace App\Models;

use Database\Factories\ActorProfileImageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'uuid',
    'actor_profile_id',
    'asset_id',
    'position',
])]
class ActorProfileImage extends Model
{
    /** @use HasFactory<ActorProfileImageFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $image): void {
            $image->uuid ??= (string) Str::uuid();
        });

        static::updating(function (self $image): void {
            if ($image->isDirty(['uuid', 'actor_profile_id', 'asset_id'])) {
                throw new LogicException('Profile image identity cannot be reassigned.');
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<ActorProfile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(ActorProfile::class, 'actor_profile_id');
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }
}
