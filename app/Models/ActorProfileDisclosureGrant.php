<?php

namespace App\Models;

use Database\Factories\ActorProfileDisclosureGrantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use LogicException;

#[Fillable(['purpose', 'expires_at'])]
class ActorProfileDisclosureGrant extends Model
{
    /** @use HasFactory<ActorProfileDisclosureGrantFactory> */
    use HasFactory;

    private bool $applyingRevocation = false;

    protected static function booted(): void
    {
        static::creating(function (self $grant): void {
            $grant->uuid ??= (string) Str::uuid();
        });

        static::updating(function (self $grant): void {
            if ($grant->isDirty([
                'uuid',
                'actor_profile_id',
                'grantee_actor_id',
                'created_by_actor_id',
                'purpose',
                'expires_at',
            ])) {
                throw new LogicException('Profile disclosure identity and terms are immutable; create a new grant instead.');
            }

            if ($grant->isDirty('revoked_at') && ! $grant->applyingRevocation) {
                throw new LogicException('Profile disclosure revocation requires the dedicated Action.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Profile disclosure grants are preserved as audit evidence; revoke them instead.');
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

    /** @return BelongsTo<Actor, $this> */
    public function grantee(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'grantee_actor_id');
    }

    /** @return BelongsTo<Actor, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Actor::class, 'created_by_actor_id');
    }

    /** @return HasMany<ActorProfileDisclosureItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ActorProfileDisclosureItem::class, 'grant_id')->orderBy('id');
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function revoke(): void
    {
        if ($this->revoked_at !== null) {
            return;
        }

        $this->applyingRevocation = true;

        try {
            $this->forceFill(['revoked_at' => now()])->save();
        } finally {
            $this->applyingRevocation = false;
        }
    }

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
