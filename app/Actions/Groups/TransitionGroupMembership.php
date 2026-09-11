<?php

namespace App\Actions\Groups;

use App\GroupRoleKey;
use App\Models\Actor;
use App\Models\GroupMembership;
use App\Models\GroupMembershipEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class TransitionGroupMembership
{
    public function __construct(
        private GroupOwnerIntegrity $ownerIntegrity,
        private GroupRoleProvisioner $roles,
    ) {}

    public function suspend(GroupMembership $membership, ?Actor $actingActor, string $reason): GroupMembership
    {
        return $this->transition($membership, 'suspended', $actingActor, $reason, false);
    }

    public function reactivate(GroupMembership $membership, ?Actor $actingActor, string $reason): GroupMembership
    {
        return $this->transition($membership, 'active', $actingActor, $reason, false);
    }

    public function leave(GroupMembership $membership, Actor $actingActor, ?string $reason = null): GroupMembership
    {
        abort_unless((int) $membership->actor_id === (int) $actingActor->id, 403);

        return $this->transition($membership, 'left', $actingActor, $reason, false);
    }

    public function remove(GroupMembership $membership, ?Actor $actingActor, string $reason): GroupMembership
    {
        return $this->transition($membership, 'removed', $actingActor, $reason, false);
    }

    public function readmit(GroupMembership $membership, ?Actor $actingActor, string $reason): GroupMembership
    {
        return $this->transition($membership, 'active', $actingActor, $reason, true);
    }

    public function recordInitial(GroupMembership $membership, ?Actor $actingActor, string $reason): void
    {
        abort_unless($membership->status === 'active', 422);

        $event = new GroupMembershipEvent;
        $event->group_membership_id = $membership->id;
        $event->group_id = $membership->group_id;
        $event->acting_actor_id = $actingActor?->id;
        $event->event = 'membership.activated';
        $event->from_status = null;
        $event->to_status = 'active';
        $event->reason = $reason;
        $event->metadata = ['initial' => true];
        $event->created_at = CarbonImmutable::now();
        $event->save();
    }

    private function transition(GroupMembership $membership, string $toStatus, ?Actor $actingActor, ?string $reason, bool $readmission): GroupMembership
    {
        $group = $membership->group()->firstOrFail();

        return $this->ownerIntegrity->execute($group, function () use ($membership, $group, $toStatus, $actingActor, $reason, $readmission): GroupMembership {
            /** @var GroupMembership $lockedMembership */
            $lockedMembership = GroupMembership::query()->whereKey($membership->id)->where('group_id', $group->id)->lockForUpdate()->firstOrFail();
            $fromStatus = $lockedMembership->status;

            $allowed = match ($fromStatus) {
                'active' => in_array($toStatus, ['suspended', 'left', 'removed'], true),
                'suspended' => in_array($toStatus, ['active', 'left', 'removed'], true) && ! $readmission,
                'left', 'removed' => $toStatus === 'active' && $readmission,
                default => false,
            };

            abort_unless($allowed, 422, "Membership cannot transition from {$fromStatus} to {$toStatus}.");

            /** @var Actor $memberActor */
            $memberActor = $lockedMembership->actor()->firstOrFail();

            if (in_array($toStatus, ['left', 'removed'], true) || $readmission) {
                $this->roles->revokeNonBaselineRoles($memberActor, $group);
            }

            DB::table('group_memberships')->where('id', $lockedMembership->id)->update([
                'status' => $toStatus,
                'updated_at' => now(),
            ]);

            if ($toStatus === 'active') {
                $this->roles->grant($memberActor, $group, $this->roles->builtInRole($group, GroupRoleKey::Member));
            }

            $event = new GroupMembershipEvent;
            $event->group_membership_id = $lockedMembership->id;
            $event->group_id = $group->id;
            $event->acting_actor_id = $actingActor?->id;
            $event->event = 'membership.'.$toStatus;
            $event->from_status = $fromStatus;
            $event->to_status = $toStatus;
            $event->reason = $reason;
            $event->metadata = ['readmission' => $readmission];
            $event->created_at = CarbonImmutable::now();
            $event->save();

            return $lockedMembership->refresh();
        });
    }
}
