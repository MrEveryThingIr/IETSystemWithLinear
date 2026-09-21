<?php

namespace App\Actions\Profile;

use App\Models\ActorProfileDisclosureGrant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RevokeProfileDisclosureGrant
{
    public function execute(User $user, ActorProfileDisclosureGrant $grant): ActorProfileDisclosureGrant
    {
        Gate::forUser($user)->authorize('manage', $grant);

        return DB::transaction(function () use ($user, $grant): ActorProfileDisclosureGrant {
            $locked = ActorProfileDisclosureGrant::query()->lockForUpdate()->findOrFail($grant->id);
            Gate::forUser($user)->authorize('manage', $locked);
            $locked->revoke();

            return $locked->fresh(['items', 'grantee.profile']) ?? $locked;
        }, 3);
    }
}
