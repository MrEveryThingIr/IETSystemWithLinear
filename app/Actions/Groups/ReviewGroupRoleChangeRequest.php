<?php

namespace App\Actions\Groups;

use App\Models\Actor;
use App\Models\GroupMembership;
use App\Models\GroupRoleChangeRequest;
use Illuminate\Support\Facades\DB;

class ReviewGroupRoleChangeRequest
{
    public function __construct(private GroupRoleProvisioner $roles) {}

    public function execute(GroupRoleChangeRequest $request, Actor $reviewer, bool $approved): GroupRoleChangeRequest
    {
        return DB::transaction(function () use ($request, $reviewer, $approved): GroupRoleChangeRequest {
            /** @var GroupRoleChangeRequest $lockedRequest */
            $lockedRequest = GroupRoleChangeRequest::query()->lockForUpdate()->findOrFail($request->id);
            abort_unless($lockedRequest->status === 'pending', 422, 'This role request has already been reviewed.');

            /** @var GroupMembership $membership */
            $membership = GroupMembership::query()->whereKey($lockedRequest->membership_id)->where('group_id', $lockedRequest->group_id)->lockForUpdate()->firstOrFail();
            abort_unless($membership->status === 'active', 422, 'Only active memberships can change roles.');
            abort_if((int) $membership->actor_id === (int) $reviewer->id, 422, 'A member cannot review their own role request.');

            $group = $membership->group()->firstOrFail();
            abort_unless(
                $group->memberships()->where('actor_id', $reviewer->id)->where('status', 'active')->exists()
                && $this->roles->hasPermission($reviewer, $group, 'approve_role_changes'),
                403,
            );
            $role = $this->roles->role($group, $lockedRequest->requested_role_id);
            abort_if($role->getAttribute('system_key') !== null, 422, 'Built-in roles cannot be changed through requests.');

            if ($approved) {
                /** @var Actor $memberActor */
                $memberActor = $membership->actor()->firstOrFail();

                if ($lockedRequest->request_type === 'grant') {
                    $this->roles->grant($memberActor, $group, $role);
                } elseif ($lockedRequest->request_type === 'revoke') {
                    $this->roles->revoke($memberActor, $group, $role);
                } else {
                    abort(422, 'Invalid role request type.');
                }
            }

            $lockedRequest->status = $approved ? 'approved' : 'rejected';
            $lockedRequest->pending_key = null;
            $lockedRequest->reviewed_by_actor_id = $reviewer->id;
            $lockedRequest->reviewed_at = now();
            $lockedRequest->review_locked_at = now();
            $lockedRequest->save();

            return $lockedRequest;
        }, attempts: 3);
    }
}
