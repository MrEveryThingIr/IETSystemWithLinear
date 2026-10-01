<?php

namespace App\Support;

use App\Models\User;
use App\PlatformRole;

final class PlatformAdmin
{
    public static function check(?User $user): bool
    {
        if (
            ! $user
            || $user->status !== 'active'
            || $user->email_verified_at === null
        ) {
            return false;
        }

        return $user->platformAccessGrants()
            ->active()
            ->where('role', PlatformRole::Superadmin->value)
            ->exists();
    }
}
