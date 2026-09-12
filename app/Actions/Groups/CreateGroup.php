<?php

namespace App\Actions\Groups;

use App\Models\Actor;
use App\Models\Group;
use Illuminate\Support\Facades\DB;

class CreateGroup
{
    public function __construct(
        private GroupRoleProvisioner $roles,
        private TransitionGroupMembership $memberships,
    ) {}

    public function execute(Actor $actor, string $name, ?string $description, string $timezone = 'UTC'): Group
    {
        return DB::transaction(function () use ($actor, $name, $description, $timezone): Group {
            $group = Group::create(['name' => $name, 'description' => $description, 'timezone' => $timezone, 'created_by_actor_id' => $actor->id]);
            $role = $this->roles->provision($group)['owner'];
            $membership = $group->memberships()->create(['actor_id' => $actor->id, 'status' => 'active']);
            $this->roles->grant($actor, $group, $role);
            $this->memberships->recordInitial($membership, $actor, 'Group creator membership established.');

            return $group;
        });
    }
}
