<?php

namespace App\Support;

use App\Models\PublicIntakePortal;
use App\Models\User;

class PublicIntakeAccess
{
    public static function canView(User $user, PublicIntakePortal $portal): bool
    {
        $portal->loadMissing('business');

        return PlatformAdmin::check($user)
            || ($portal->business !== null && BusinessAccess::canView($user, $portal->business))
            || $portal->grants()->where('user_id', $user->getKey())->exists();
    }

    public static function canManage(User $user, PublicIntakePortal $portal): bool
    {
        $portal->loadMissing('business');

        return PlatformAdmin::check($user)
            || ($portal->business !== null && BusinessAccess::canManage($user, $portal->business))
            || $portal->grants()
                ->where('user_id', $user->getKey())
                ->where('role', 'manager')
                ->exists();
    }
}
