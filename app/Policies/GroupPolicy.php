<?php

namespace App\Policies;

use App\Actions\Groups\GroupRoleProvisioner;
use App\Models\Actor;
use App\Models\Group;
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

    private function allows(User $user, Group $group, string $permission): bool
    {
        $actor = $user->actor;
        if (! $actor instanceof Actor) { return false; }

        return $group->memberships()->where('actor_id', $actor->id)->where('status', 'active')->exists()
            && $this->groupRoles->hasPermission($actor, $group, $permission);
    }
}
