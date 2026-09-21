<?php

namespace App\Actions\Profile;

use App\Models\Actor;
use App\Models\ActorProfile;
use App\Models\ActorProfileDisclosureGrant;
use App\Models\ActorProfileDisclosureItem;
use App\Models\User;
use App\Support\Profile\ProfileDisclosureCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CreateProfileDisclosureGrant
{
    /** @param list<string> $itemKeys */
    public function execute(
        User $user,
        ActorProfile $profile,
        ActorProfile $recipientProfile,
        array $itemKeys,
        ?string $purpose = null,
        ?int $expiresInDays = 30,
    ): ActorProfileDisclosureGrant {
        Gate::forUser($user)->authorize('update', $profile);

        if ($expiresInDays !== null && ! in_array($expiresInDays, [7, 30, 90], true)) {
            throw ValidationException::withMessages([
                'expiryDays' => __('ui.profile_sharing.invalid_expiry'),
            ]);
        }

        $profile->loadMissing('actor');
        $recipientProfile->loadMissing('actor.user');
        $recipient = $recipientProfile->actor;
        $recipientUser = $recipient->user;

        if ((int) $recipient->id === (int) $profile->actor_id
            || $recipient->status !== 'active'
            || ! $recipientUser instanceof User
            || $recipientUser->status !== 'active'
            || $recipientUser->email_verified_at === null) {
            throw ValidationException::withMessages([
                'recipientProfileId' => __('ui.profile_sharing.invalid_recipient'),
            ]);
        }

        $selected = array_values(array_unique(array_filter(
            array_map(static fn (mixed $key): string => trim((string) $key), $itemKeys),
            static fn (string $key): bool => $key !== '',
        )));

        $catalog = app(ProfileDisclosureCatalog::class);
        $valid = $catalog->validKeys($profile);

        if ($selected === [] || array_diff($selected, $valid) !== []) {
            throw ValidationException::withMessages([
                'selectedItems' => __('ui.profile_sharing.invalid_items'),
            ]);
        }

        return DB::transaction(function () use (
            $user,
            $profile,
            $recipient,
            $selected,
            $purpose,
            $expiresInDays,
            $catalog,
        ): ActorProfileDisclosureGrant {
            $lockedProfile = ActorProfile::query()->lockForUpdate()->findOrFail($profile->id);
            Gate::forUser($user)->authorize('update', $lockedProfile);
            $owner = Actor::query()->findOrFail($lockedProfile->actor_id);
            $stillValid = $catalog->validKeys($lockedProfile);

            if (array_diff($selected, $stillValid) !== []) {
                throw ValidationException::withMessages([
                    'selectedItems' => __('ui.profile_sharing.invalid_items'),
                ]);
            }

            $grant = new ActorProfileDisclosureGrant;
            $grant->profile()->associate($lockedProfile);
            $grant->grantee()->associate($recipient);
            $grant->creator()->associate($owner);
            $grant->purpose = filled($purpose) ? trim((string) $purpose) : null;
            $grant->expires_at = $expiresInDays === null ? null : now()->addDays($expiresInDays);
            $grant->save();

            foreach ($selected as $key) {
                $item = new ActorProfileDisclosureItem([
                    'kind' => $catalog->kindForKey($key),
                    'item_key' => $key,
                ]);
                $grant->items()->save($item);
            }

            return $grant->load(['items', 'grantee.profile']);
        }, 3);
    }
}
