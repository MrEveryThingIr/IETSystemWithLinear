<?php

namespace App\Actions\Groups;

use App\Models\Actor;
use App\Models\Group;
use Illuminate\Support\Facades\DB;

class CreateGroup
{
    public function __construct(private GroupRoleProvisioner $roles) {}

    public function execute(Actor $actor, string $name, ?string $description): Group
    {
        return DB::transaction(function () use ($actor, $name, $description): Group {
            $group = Group::create(['name' => $name, 'description' => $description, 'created_by_actor_id' => $actor->id]);
            $role = $this->roles->provision($group)['owner'];
            $group->memberships()->create(['actor_id' => $actor->id, 'status' => 'active']);
            $this->roles->assign($actor, $group, $role);

            return $group;
        });
    }
}
