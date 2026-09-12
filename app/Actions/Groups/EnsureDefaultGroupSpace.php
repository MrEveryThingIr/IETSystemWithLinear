<?php

namespace App\Actions\Groups;

use App\Models\Actor;
use App\Models\Group;
use App\Models\GroupSpace;

class EnsureDefaultGroupSpace
{
    public function execute(Group $group, Actor $creator): GroupSpace
    {
        abort_unless((int) $group->created_by_actor_id === (int) $creator->id, 422, 'The default space must be provisioned by the group creator.');

        return GroupSpace::query()->firstOrCreate(
            [
                'group_id' => $group->id,
                'slug' => 'general',
            ],
            [
                'created_by_actor_id' => $creator->id,
                'name' => 'General',
                'kind' => 'chat',
                'status' => 'active',
                'is_default' => true,
            ],
        );
    }
}
