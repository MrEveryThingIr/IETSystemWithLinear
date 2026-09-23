<?php

namespace Database\Factories;

use App\Models\AccessInvitation;
use App\Models\AccessInvitationAcceptance;
use App\Models\Actor;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AccessInvitationAcceptance> */
class AccessInvitationAcceptanceFactory extends Factory
{
    public function definition(): array
    {
        $actor = Actor::factory();

        return [
            'access_invitation_id' => AccessInvitation::factory(),
            'user_id' => fn (array $attributes): int => Actor::query()
                ->findOrFail($attributes['actor_id'])
                ->user_id,
            'actor_id' => $actor,
            'accepted_at' => now(),
        ];
    }
}
