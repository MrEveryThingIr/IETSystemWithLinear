<?php

namespace App\Actions\Profile;

use App\Models\ActorProfile;
use App\Models\ActorProfileImage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SetDisplayedProfileImage
{
    public function execute(User $user, ActorProfile $profile, ?ActorProfileImage $image): ActorProfile
    {
        Gate::forUser($user)->authorize('update', $profile);

        return DB::transaction(function () use ($user, $profile, $image): ActorProfile {
            $lockedProfile = ActorProfile::query()->lockForUpdate()->findOrFail($profile->id);
            Gate::forUser($user)->authorize('update', $lockedProfile);

            $lockedImage = null;

            if ($image instanceof ActorProfileImage) {
                $lockedImage = ActorProfileImage::query()
                    ->with('asset')
                    ->where('actor_profile_id', $lockedProfile->id)
                    ->lockForUpdate()
                    ->findOrFail($image->id);

                abort_unless($lockedImage->asset->isReadyForPublication(), 422, 'Profile image is not ready to display yet.');
            }

            $lockedProfile->setDisplayImage($lockedImage);

            return $lockedProfile->refresh();
        }, 3);
    }
}
