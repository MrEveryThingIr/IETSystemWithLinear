<?php

namespace App\Actions\Groups;

use App\Models\Actor;
use App\Models\GroupMembership;
use App\Models\GroupRoleChangeRequest;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class SubmitGroupRoleChangeRequest
{
    public function __construct(private GroupRoleProvisioner $roles) {}

    public function execute(GroupMembership $membership, Actor $requester, Role $role, string $requestType): GroupRoleChangeRequest
    {
        abort_unless(in_array($requestType, ['grant', 'revoke'], true), 422, 'Invalid role request type.');
        abort_unless((int) $membership->actor_id === (int) $requester->id && $membership->status === 'active', 403);
        abort_if($role->getAttribute('system_key') !== null, 422, 'Built-in roles cannot be requested.');

        $group = $membership->group()->firstOrFail();
        abort_unless((int) $role->getAttribute('group_id') === (int) $group->id, 422);

        $hasRole = $this->roles->hasRole($requester, $group, $role->name);
        abort_if($requestType === 'grant' && $hasRole, 422, 'The member already has this role.');
        abort_if($requestType === 'revoke' && ! $hasRole, 422, 'The member does not have this role.');

        return DB::transaction(function () use ($membership, $group, $role, $requestType): GroupRoleChangeRequest {
            GroupMembership::query()->whereKey($membership->id)->where('status', 'active')->lockForUpdate()->firstOrFail();
            $pendingKey = implode(':', [$membership->id, $role->id, $requestType]);

            $existing = GroupRoleChangeRequest::query()
                ->where('pending_key', $pendingKey)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof GroupRoleChangeRequest) {
                return $existing;
            }

            return GroupRoleChangeRequest::create([
                'group_id' => $group->id,
                'membership_id' => $membership->id,
                'requested_role_id' => $role->id,
                'request_type' => $requestType,
                'pending_key' => $pendingKey,
                'status' => 'pending',
            ]);
        }, attempts: 3);
    }
}
