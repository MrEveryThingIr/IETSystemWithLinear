<?php

namespace Database\Factories;

use App\Models\Actor;
use App\Models\GroupInvitation;
use App\Models\GroupInvitationAcceptance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GroupInvitationAcceptance>
 */
class GroupInvitationAcceptanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_invitation_id' => GroupInvitation::factory(),
            'accepted_by_actor_id' => Actor::factory(),
            'accepted_at' => now(),
        ];
    }
}
