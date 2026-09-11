<?php

namespace App\Models;

use App\PlatformCapability;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['username', 'email', 'locale', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements HasLocalePreference, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /** @return HasOne<Actor, $this> */
    public function actor(): HasOne
    {
        return $this->hasOne(Actor::class);
    }

    /** @return HasMany<PlatformAccessGrant, $this> */
    public function platformAccessGrants(): HasMany
    {
        return $this->hasMany(PlatformAccessGrant::class);
    }

    public function hasPlatformCapability(PlatformCapability $capability): bool
    {
        $currentUser = self::query()->find($this->getKey());

        if (! $currentUser instanceof self || $currentUser->status !== 'active' || $currentUser->email_verified_at === null) {
            return false;
        }

        return $currentUser->platformAccessGrants()
            ->active()
            ->get(['role'])
            ->contains(fn (PlatformAccessGrant $grant): bool => $grant->role->grants($capability));
    }

    public function preferredLocale(): ?string
    {
        return $this->locale;
    }
}
