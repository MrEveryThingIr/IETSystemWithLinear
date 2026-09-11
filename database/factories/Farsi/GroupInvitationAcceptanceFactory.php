<?php

namespace Database\Factories\Farsi;

use App\Models\GroupInvitationAcceptance;
use Database\Factories\GroupInvitationAcceptanceFactory as BaseGroupInvitationAcceptanceFactory;

class GroupInvitationAcceptanceFactory extends BaseGroupInvitationAcceptanceFactory
{
    protected $model = GroupInvitationAcceptance::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_invitation_id' => GroupInvitationFactory::new(),
            'accepted_by_actor_id' => ActorFactory::new(),
            'accepted_at' => now(),
        ];
    }
}
