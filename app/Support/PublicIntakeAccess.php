<?php

namespace App\Support;

use App\Models\PublicIntakePortal;
use App\Models\User;

class PublicIntakeAccess
{
    public static function canView(User $user, PublicIntakePortal $portal): bool
    {
        return PlatformAdmin::check($user)
            || $portal->grants()->where('user_id', $user->getKey())->exists();
    }

    public static function canManage(User $user, PublicIntakePortal $portal): bool
    {
        return PlatformAdmin::check($user)
            || $portal->grants()
                ->where('user_id', $user->getKey())
                ->where('role', 'manager')
                ->exists();
    }
}
