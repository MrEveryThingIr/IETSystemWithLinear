<?php

namespace App\Services\Surfaces;

use App\Models\FeatureSurfaceGrant;
use App\Models\User;
use App\Support\PlatformAdmin;
use App\Support\RequestMemo;

class FeatureSurfaceAccess
{
    public function __construct(
        private readonly FeatureSurfaceRegistry $registry,
    ) {}

    public function allows(?User $user, string $surfaceKey): bool
    {
        if (
            ! $user
            || $user->status !== 'active'
            || $user->email_verified_at === null
        ) {
            return false;
        }

        if (PlatformAdmin::check($user)) {
            return true;
        }

        return in_array($surfaceKey, $this->grantedKeys($user), true);
    }

    public function grantedKeys(User $user): array
    {
        if (PlatformAdmin::check($user)) {
            return $this->registry->keys();
        }

        return RequestMemo::remember('surfaces:'.$user->getKey(), fn (): array => FeatureSurfaceGrant::query()
            ->where('user_id', $user->getKey())
            ->pluck('surface_key')
            ->all());
    }

    /**
     * Compatibility method for audit/UI code.
     *
     * Unified publication is now always enforced.
     */
    public function strict(): bool
    {
        return true;
    }
}
