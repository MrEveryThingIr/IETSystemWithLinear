<?php

namespace App\Actions\Groups;

use App\Models\GroupMembership;

class RemoveGroupMember
{
    public function __construct(private GroupOwnerIntegrity $ownerIntegrity, private GroupRoleProvisioner $roles) {}
    public function handle(GroupMembership $membership): void
    {
        $group = $membership->group()->firstOrFail();
        $this->ownerIntegrity->execute($group, function () use ($group, $membership): void {
            $membership = GroupMembership::query()->whereKey($membership->getKey())->where('group_id', $group->getKey())->lockForUpdate()->firstOrFail();
            $membership->update(['status' => 'removed']);
            $this->roles->clear($membership->actor()->firstOrFail(), $group);
        });
    }
}
