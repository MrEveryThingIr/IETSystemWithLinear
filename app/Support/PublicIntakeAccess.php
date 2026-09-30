<?php

namespace App\Support;

use App\Models\PublicIntakePortal;
use App\Models\User;

class PublicIntakeAccess
{
    public static function canView(User $user, PublicIntakePortal $portal): bool
    {
        return self::isSuperAdmin($user)
            || $portal->grants()->where('user_id', $user->getKey())->exists();
    }

    public static function canManage(User $user, PublicIntakePortal $portal): bool
    {
        return self::isSuperAdmin($user)
            || $portal->grants()
                ->where('user_id', $user->getKey())
                ->where('role', 'manager')
                ->exists();
    }

    private static function isSuperAdmin(User $user): bool
    {
        if (method_exists($user, 'isSuperAdmin')) {
            try {
                if ((bool) $user->isSuperAdmin()) {
                    return true;
                }
            } catch (\Throwable) {
            }
        }

        if (isset($user->is_super_admin) && (bool) $user->is_super_admin) {
            return true;
        }

        if (method_exists($user, 'hasRole')) {
            foreach (['super-admin', 'superadmin', 'Super Admin'] as $role) {
                try {
                    if ($user->hasRole($role)) {
                        return true;
                    }
                } catch (\Throwable) {
                }
            }
        }

        return false;
    }
}
