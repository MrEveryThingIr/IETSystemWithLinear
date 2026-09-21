<?php

namespace App\Actions\Profile;

use App\Models\ActorProfile;
use App\Models\ActorProfileImage;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class RemoveActorProfileImage
{
    public function execute(User $user, ActorProfile $profile, ActorProfileImage $image): void
    {
        Gate::forUser($user)->authorize('update', $profile);

        $storage = DB::transaction(function () use ($user, $profile, $image): array {
            $lockedProfile = ActorProfile::query()->lockForUpdate()->findOrFail($profile->id);
            Gate::forUser($user)->authorize('update', $lockedProfile);

            $lockedImage = ActorProfileImage::query()
                ->where('actor_profile_id', $lockedProfile->id)
                ->lockForUpdate()
                ->findOrFail($image->id);
            $asset = Asset::query()->lockForUpdate()->findOrFail($lockedImage->asset_id);

            if ((int) $lockedProfile->display_profile_image_id === (int) $lockedImage->id) {
                $lockedProfile->setDisplayImage(null);
            }

            $lockedImage->delete();
            $asset->delete();

            return ['disk' => $asset->disk, 'key' => $asset->storage_key];
        }, 3);

        if (is_array($storage)) {
            Storage::disk((string) $storage['disk'])->delete((string) $storage['key']);
        }
    }
}
