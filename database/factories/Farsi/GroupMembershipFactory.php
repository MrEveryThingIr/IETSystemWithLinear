<?php

namespace Database\Factories\Farsi;

use App\Models\GroupMembership;
use Database\Factories\GroupMembershipFactory as BaseGroupMembershipFactory;

class GroupMembershipFactory extends BaseGroupMembershipFactory
{
    protected $model = GroupMembership::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_id' => GroupFactory::new(),
            'actor_id' => ActorFactory::new(),
            'role' => 'member',
            'status' => 'active',
        ];
    }
}
