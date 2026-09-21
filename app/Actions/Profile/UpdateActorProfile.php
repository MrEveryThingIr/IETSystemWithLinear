<?php

namespace App\Actions\Profile;

use App\Models\ActorProfile;
use App\Models\User;
use App\ProfileVisibility;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UpdateActorProfile
{
    /**
     * @param array<string, mixed> $input
     */
    public function execute(User $user, ActorProfile $profile, array $input): ActorProfile
    {
        Gate::forUser($user)->authorize('update', $profile);

        $data = Validator::make($input, [
            'display_name' => ['nullable', 'string', 'max:120'],
            'headline' => ['nullable', 'string', 'max:180'],
            'bio' => ['nullable', 'string', 'max:3000'],
            'location_text' => ['nullable', 'string', 'max:180'],
            'website_url' => ['nullable', 'url:http,https', 'max:500'],
            'visibility' => ['required', Rule::enum(ProfileVisibility::class)],
        ])->validate();

        foreach (['display_name', 'headline', 'bio', 'location_text', 'website_url'] as $field) {
            $value = isset($data[$field]) ? trim((string) $data[$field]) : null;
            $data[$field] = $value === '' ? null : $value;
        }

        $profile->update($data);

        return $profile->refresh();
    }
}
