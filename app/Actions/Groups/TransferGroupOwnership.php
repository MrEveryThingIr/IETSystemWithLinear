<?php

namespace App\Actions\Groups;

use App\GroupRoleKey;
use App\Models\Actor;
use App\Models\Group;
use App\Models\GroupMembership;
use App\Models\GroupOwnershipTransferRequest;
use Illuminate\Support\Facades\DB;

class TransferGroupOwnership
{
    public function __construct(
        private GroupOwnerIntegrity $ownerIntegrity,
        private GroupRoleProvisioner $roles,
    ) {}

    public function propose(Group $group, Actor $currentOwner, GroupMembership $targetMembership): GroupOwnershipTransferRequest
    {
        abort_unless($group->memberships()->where('actor_id', $currentOwner->id)->where('status', 'active')->exists(), 403);
        abort_unless($this->roles->hasBuiltInRole($currentOwner, $group, GroupRoleKey::Owner), 403);
        abort_unless((int) $targetMembership->group_id === (int) $group->id && $targetMembership->status === 'active', 422);
        abort_if((int) $targetMembership->actor_id === (int) $currentOwner->id, 422, 'Choose another active member.');
        /** @var Actor $targetActor */
        $targetActor = $targetMembership->actor()->firstOrFail();
        abort_if($this->roles->hasBuiltInRole($targetActor, $group, GroupRoleKey::Owner), 422, 'This member is already an Owner.');

        return DB::transaction(function () use ($group, $currentOwner, $targetMembership): GroupOwnershipTransferRequest {
            $sourceMembership = GroupMembership::query()->where('group_id', $group->id)->where('actor_id', $currentOwner->id)->where('status', 'active')->lockForUpdate()->firstOrFail();
            GroupMembership::query()->whereKey($targetMembership->id)->where('status', 'active')->lockForUpdate()->firstOrFail();

            return GroupOwnershipTransferRequest::query()->firstOrCreate(
                ['pending_group_id' => $group->id],
                ['group_id' => $group->id, 'from_membership_id' => $sourceMembership->id, 'to_membership_id' => $targetMembership->id, 'status' => 'pending'],
            );
        }, attempts: 3);
    }

    public function respond(GroupOwnershipTransferRequest $request, Actor $responder, bool $accepted): GroupOwnershipTransferRequest
    {
        return DB::transaction(function () use ($request, $responder, $accepted): GroupOwnershipTransferRequest {
            $request = GroupOwnershipTransferRequest::query()->lockForUpdate()->findOrFail($request->id);
            abort_unless($request->status === 'pending', 422, 'This ownership transfer has already been answered.');
            $targetMembership = GroupMembership::query()->whereKey($request->to_membership_id)->where('status', 'active')->lockForUpdate()->firstOrFail();
            abort_unless((int) $targetMembership->actor_id === (int) $responder->id, 403);

            if ($accepted) {
                $group = Group::query()->findOrFail($request->group_id);
                $sourceMembership = GroupMembership::query()->whereKey($request->from_membership_id)->where('status', 'active')->lockForUpdate()->firstOrFail();
                /** @var Actor $currentOwner */
                $currentOwner = $sourceMembership->actor()->firstOrFail();
                abort_unless($this->roles->hasBuiltInRole($currentOwner, $group, GroupRoleKey::Owner), 422, 'The requesting member is no longer an Owner.');
                $this->ownerIntegrity->execute($group, function () use ($group, $currentOwner, $targetMembership): void {
                    /** @var Actor $targetActor */
                    $targetActor = $targetMembership->actor()->firstOrFail();
                    $ownerRole = $this->roles->builtInRole($group, GroupRoleKey::Owner);
                    $this->roles->grant($targetActor, $group, $ownerRole);
                    $this->roles->revoke($currentOwner, $group, $ownerRole);
                });
            }

            $request->update(['status' => $accepted ? 'accepted' : 'rejected', 'pending_group_id' => null, 'responded_at' => now()]);

            return $request;
        }, attempts: 3);
    }
}
