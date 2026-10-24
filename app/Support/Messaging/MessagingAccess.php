<?php

namespace App\Support\Messaging;

use App\Models\User;
use App\Services\Surfaces\FeatureSurfaceAccess;

final class MessagingAccess
{
    /** Whether Messages is published for this person. */
    public static function canUseMessages(?User $user): bool
    {
        return $user instanceof User && app(FeatureSurfaceAccess::class)->allows($user, 'messages');
    }
}
