<?php

namespace App\Actions\Groups;

use App\Actions\Contexts\EnsureGroupSpaceContext;
use App\Models\Actor;
use App\Models\Group;
use App\Models\GroupSpace;

class EnsureDefaultGroupSpace
{
    public function __construct(private readonly EnsureGroupSpaceContext $contexts) {}

    public function execute(Group $group, Actor $creator): GroupSpace
    {
        abort_unless((int) $group->created_by_actor_id === (int) $creator->id, 422, 'The default space must be provisioned by the group creator.');

        $space = GroupSpace::query()->firstOrCreate(
            [
                'group_id' => $group->id,
                'slug' => 'general',
            ],
            [
                'created_by_actor_id' => $creator->id,
                'name' => 'General',
                'kind' => 'chat',
                'access_mode' => 'group',
                'status' => 'active',
                'is_default' => true,
            ],
        );

        $space->forceFill([
            'name' => 'General',
            'kind' => 'chat',
            'access_mode' => 'group',
            'status' => 'active',
            'is_default' => true,
        ]);

        if ($space->isDirty()) {
            $space->save();
        }

        $this->contexts->execute($space);

        return $space->refresh();
    }
}
