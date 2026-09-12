<?php

namespace App\Policies;

use App\Actions\Groups\GroupRoleProvisioner;
use App\GroupPermission;
use App\GroupRoleKey;
use App\Models\Actor;
use App\Models\Group;
use App\Models\GroupAgreementVersion;
use App\Models\GroupMembership;
use App\Models\MembershipAgreementAcceptance;
use App\Models\User;
use App\PlatformCapability;

class GroupPolicy
{
    public function __construct(private readonly GroupRoleProvisioner $groupRoles) {}

    public function view(User $user, Group $group): bool
    {
        return $this->allows($user, $group, GroupPermission::Participate->value);
    }

    public function create(User $user): bool
    {
        return $user->hasPlatformCapability(PlatformCapability::CreateGroups);
    }

    public function update(User $user, Group $group): bool
    {
        return $this->allows($user, $group, GroupPermission::ManageGroup->value);
    }

    public function createInvitation(User $user, Group $group): bool
    {
        return $this->allows($user, $group, GroupPermission::ManageInvitations->value);
    }

    public function manageRoles(User $user, Group $group): bool
    {
        return $this->allows($user, $group, GroupPermission::ManageRoles->value);
    }

    public function requestRole(User $user, Group $group): bool
    {
        return $this->allows($user, $group, GroupPermission::Participate->value);
    }

    public function approveRoleChanges(User $user, Group $group): bool
    {
        return $this->allows($user, $group, GroupPermission::ApproveRoleChanges->value);
    }

    public function manageMembers(User $user, Group $group): bool
    {
        return $this->allows($user, $group, GroupPermission::ManageMembers->value);
    }

    public function manageAdmissions(User $user, Group $group): bool
    {
        return $this->allows($user, $group, GroupPermission::ManageAdmissions->value);
    }

    public function manageAgreements(User $user, Group $group): bool
    {
        return $this->allows($user, $group, GroupPermission::ManageAgreements->value);
    }

    public function viewGroupAudit(User $user, Group $group): bool
    {
        return $this->allows($user, $group, GroupPermission::ViewGroupAudit->value);
    }

    public function manageSimulations(User $user, Group $group): bool
    {
        return $this->allows($user, $group, GroupPermission::ManageSimulations->value);
    }

    public function transferOwnership(User $user, Group $group): bool
    {
        $actor = $user->actor;

        return $actor instanceof Actor
            && $this->allows($user, $group, GroupPermission::TransferOwnership->value)
            && $this->groupRoles->hasBuiltInRole($actor, $group, GroupRoleKey::Owner);
    }

    private function allows(User $user, Group $group, string $permission): bool
    {
        $current = $user->fresh();
        if (! $current instanceof User || $current->status !== 'active' || ! $current->actor instanceof Actor) {
            return false;
        }

        $membership = $group->memberships()->where('actor_id', $current->actor->id)->where('status', 'active')->first();
        if ($membership === null || ! $this->groupRoles->hasPermission($current->actor, $group, $permission)) {
            return false;
        }

        return $permission !== GroupPermission::Participate->value || ! $this->requiresReacceptance($group, $membership);
    }

    private function requiresReacceptance(Group $group, GroupMembership $membership): bool
    {
        $requiredVersionIds = GroupAgreementVersion::query()
            ->whereHas('agreement', fn ($query) => $query->where('group_id', $group->id))
            ->where('status', 'active')
            ->where('reacceptance_required', true)
            ->where('effective_from', '<=', now())
            ->where(fn ($query) => $query->whereNull('effective_until')->orWhere('effective_until', '>', now()))
            ->pluck('id');

        if ($requiredVersionIds->isEmpty()) {
            return false;
        }

        $currentMembershipEvent = $membership->currentParticipationEvent();

        if ($currentMembershipEvent === null) {
            return true;
        }

        $acceptedVersionCount = MembershipAgreementAcceptance::query()
            ->where('group_membership_id', $membership->id)
            ->where('group_membership_event_id', $currentMembershipEvent->id)
            ->whereIn('group_agreement_version_id', $requiredVersionIds)
            ->distinct('group_agreement_version_id')
            ->count('group_agreement_version_id');

        return $acceptedVersionCount !== $requiredVersionIds->count();
    }
}
