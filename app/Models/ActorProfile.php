<?php

namespace App\Models;

use App\ProfileVisibility;
use Database\Factories\ActorProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

#[Fillable([
    'display_name',
    'headline',
    'bio',
    'location_text',
    'website_url',
    'visibility',
])]
class ActorProfile extends Model
{
    /** @use HasFactory<ActorProfileFactory> */
    use HasFactory;

    protected $attributes = [
        'visibility' => ProfileVisibility::Private->value,
    ];

    private bool $applyingDisplayImage = false;

    protected static function booted(): void
    {
        static::creating(function (self $profile): void {
            $profile->public_id ??= (string) Str::uuid();
        });

        static::updating(function (self $profile): void {
            if ($profile->isDirty(['actor_id', 'public_id'])) {
                throw new LogicException('Profile identity cannot be reassigned.');
            }

            if ($profile->isDirty('display_profile_image_id') && ! $profile->applyingDisplayImage) {
                throw new LogicException('Displayed profile image changes require the dedicated profile action.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Actor Profiles are preserved with Actor identity.');
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /** @return BelongsTo<Actor, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(Actor::class);
    }

    /** @return HasMany<ActorProfileImage, $this> */
    public function images(): HasMany
    {
        return $this->hasMany(ActorProfileImage::class)->orderBy('position')->orderBy('id');
    }

    /** @return HasMany<ActorProfileIntent, $this> */
    public function intents(): HasMany
    {
        return $this->hasMany(ActorProfileIntent::class);
    }

    /** @return HasMany<ActorProfileDisclosureGrant, $this> */
    public function disclosureGrants(): HasMany
    {
        return $this->hasMany(ActorProfileDisclosureGrant::class, 'actor_profile_id');
    }

    /** @return BelongsTo<ActorProfileImage, $this> */
    public function displayImage(): BelongsTo
    {
        return $this->belongsTo(ActorProfileImage::class, 'display_profile_image_id');
    }

    public function setDisplayImage(?ActorProfileImage $image): void
    {
        if ($image instanceof ActorProfileImage && (int) $image->actor_profile_id !== (int) $this->id) {
            throw new LogicException('Displayed image must belong to this profile.');
        }

        $this->applyingDisplayImage = true;

        try {
            $this->forceFill(['display_profile_image_id' => $image?->id])->save();
        } finally {
            $this->applyingDisplayImage = false;
        }
    }

    protected function casts(): array
    {
        return [
            'visibility' => ProfileVisibility::class,
        ];
    }
}
