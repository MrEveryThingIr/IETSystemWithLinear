<?php

namespace App\Actions\Profile;

use App\Models\Actor;
use App\Models\ActorProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class EnsureActorProfile
{
    public function execute(User $user): ActorProfile
    {
        $current = User::query()->with('actor')->find($user->id);

        abort_unless(
            $current instanceof User
            && $current->status === 'active'
            && $current->email_verified_at !== null
            && $current->actor instanceof Actor
            && $current->actor->status === 'active',
            403,
        );

        return DB::transaction(function () use ($current): ActorProfile {
            $actor = Actor::query()->lockForUpdate()->findOrFail($current->actor->id);

            $profile = ActorProfile::query()
                ->where('actor_id', $actor->id)
                ->first();

            return $profile instanceof ActorProfile
                ? $profile
                : $actor->profile()->create();
        }, 3);
    }
}
