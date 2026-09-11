<?php

namespace App\Actions\Groups;

use App\GroupRoleKey;
use App\Models\Actor;
use App\Models\Group;
use App\Models\GroupMembership;

class TransferGroupOwnership
{
    public function __construct(
        private GroupOwnerIntegrity $ownerIntegrity,
        private GroupRoleProvisioner $roles,
    ) {}

    public function execute(Group $group, Actor $currentOwner, GroupMembership $targetMembership): void
    {
        abort_unless($group->memberships()->where('actor_id', $currentOwner->id)->where('status', 'active')->exists(), 403);
        abort_unless($this->roles->hasBuiltInRole($currentOwner, $group, GroupRoleKey::Owner), 403);
        abort_unless((int) $targetMembership->group_id === (int) $group->id && $targetMembership->status === 'active', 422);
        abort_if((int) $targetMembership->actor_id === (int) $currentOwner->id, 422, 'Choose another active member.');

        $this->ownerIntegrity->execute($group, function () use ($group, $currentOwner, $targetMembership): void {
            /** @var Actor $targetActor */
            $targetActor = $targetMembership->actor()->firstOrFail();
            $ownerRole = $this->roles->builtInRole($group, GroupRoleKey::Owner);
            $this->roles->grant($targetActor, $group, $ownerRole);
            $this->roles->revoke($currentOwner, $group, $ownerRole);
        });
    }
}
