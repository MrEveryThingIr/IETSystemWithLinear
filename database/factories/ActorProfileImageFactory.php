<?php

namespace Database\Factories;

use App\Models\ActorProfile;
use App\Models\ActorProfileImage;
use App\Models\Asset;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ActorProfileImage> */
class ActorProfileImageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'actor_profile_id' => ActorProfile::factory(),
            'asset_id' => Asset::factory()->state([
                'group_space_id' => null,
                'mime_type' => 'image/png',
                'extension' => 'png',
                'original_filename' => 'profile.png',
                'rights_status' => 'owned',
            ]),
            'position' => 0,
        ];
    }
}
