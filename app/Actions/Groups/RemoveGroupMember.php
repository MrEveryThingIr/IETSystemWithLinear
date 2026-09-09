<?php

namespace App\Actions\Groups;

use App\Models\GroupMembership;

class RemoveGroupMember
{
    public function __construct(private GroupOwnerIntegrity $ownerIntegrity) {}

    public function handle(GroupMembership $membership): void
    {
        $group = $membership->group()->firstOrFail();

        $this->ownerIntegrity->execute($group, function () use ($group, $membership): void {
            GroupMembership::query()
                ->whereKey($membership->getKey())
                ->where('group_id', $group->getKey())
                ->lockForUpdate()
                ->firstOrFail()
                ->update(['status' => 'removed']);
        });
    }
}
