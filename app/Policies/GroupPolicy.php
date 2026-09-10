<?php

namespace App\Policies;

use App\Actions\Groups\GroupRoleProvisioner;
use App\Models\Actor;
use App\Models\Group;
use App\Models\GroupAgreementVersion;
use App\Models\MembershipAgreementAcceptance;
use App\Models\User;

class GroupPolicy
{
    public function __construct(private readonly GroupRoleProvisioner $groupRoles) {}

    public function view(User $user, Group $group): bool { return $this->allows($user, $group, 'participate'); }
    public function update(User $user, Group $group): bool { return $this->allows($user, $group, 'manage_group'); }
    public function createInvitation(User $user, Group $group): bool { return $this->allows($user, $group, 'manage_invitations'); }
    public function manageRoles(User $user, Group $group): bool { return $this->allows($user, $group, 'manage_roles'); }
    public function requestRole(User $user, Group $group): bool { return $this->allows($user, $group, 'participate'); }
    public function approveRoleChanges(User $user, Group $group): bool { return $this->allows($user, $group, 'approve_role_changes'); }
    public function manageMembers(User $user, Group $group): bool { return $this->allows($user, $group, 'manage_members'); }
    public function manageAdmissions(User $user, Group $group): bool { return $this->allows($user, $group, 'manage_members'); }
    public function manageAgreements(User $user, Group $group): bool { return $this->allows($user, $group, 'manage_group'); }

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

        return $permission !== 'participate' || ! $this->requiresReacceptance($group, $membership->id);
    }

    private function requiresReacceptance(Group $group, int $membershipId): bool
    {
        $requiredVersionIds = GroupAgreementVersion::query()
            ->whereHas('agreement', fn ($query) => $query->where('group_id', $group->id))
            ->where('status', 'active')
            ->where('reacceptance_required', true)
            ->where('effective_from', '<=', now())
            ->where(fn ($query) => $query->whereNull('effective_until')->orWhere('effective_until', '>', now()))
            ->pluck('id');

        return $requiredVersionIds->isNotEmpty()
            && MembershipAgreementAcceptance::query()->where('group_membership_id', $membershipId)->whereIn('group_agreement_version_id', $requiredVersionIds)->count() !== $requiredVersionIds->count();
    }
}
