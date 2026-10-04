<?php

namespace App\Support;

use App\Models\Business;
use App\Models\BusinessMembership;
use App\Models\User;

final class BusinessAccess
{
    public static function canView(User $user, Business $business): bool
    {
        if (PlatformAdmin::check($user) || $business->visibility === 'public') {
            return true;
        }

        return self::membership($user, $business) !== null;
    }

    public static function canOperate(User $user, Business $business): bool
    {
        if (PlatformAdmin::check($user)) {
            return true;
        }

        return self::membership($user, $business) !== null;
    }

    public static function canManage(User $user, Business $business): bool
    {
        if (PlatformAdmin::check($user)) {
            return true;
        }

        if ((int) $business->owner_actor_id === (int) $user->actor?->getKey()) {
            return true;
        }

        $membership = self::membership($user, $business);

        return $membership && in_array($membership->role, ['owner', 'manager'], true);
    }

    public static function canManageOwnership(User $user, Business $business): bool
    {
        return (int) $business->owner_actor_id === (int) $user->actor?->getKey();
    }

    public static function membership(User $user, Business $business): ?BusinessMembership
    {
        if (! $user->actor) {
            return null;
        }

        return $business->memberships()
            ->where('actor_id', $user->actor->getKey())
            ->where('status', 'active')
            ->first();
    }
}
