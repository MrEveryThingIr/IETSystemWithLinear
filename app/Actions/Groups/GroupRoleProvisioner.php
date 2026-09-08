<?php

namespace App\Actions\Groups;

use App\Models\Actor;
use App\Models\Group;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class GroupRoleProvisioner
{
    /** @var list<string> */
    private const OWNER_PERMISSIONS = ['manage_group', 'manage_members', 'manage_roles', 'manage_invitations', 'approve_role_changes', 'participate'];

    /** @var list<string> */
    private const MEMBER_PERMISSIONS = ['participate'];

    /** @return list<string> */
    public static function permissionNames(): array
    {
        return array_values(array_unique([...self::OWNER_PERMISSIONS, ...self::MEMBER_PERMISSIONS]));
    }

    public function seedPermissions(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::permissionNames() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
    }

    /** @return array{owner: Role, member: Role} */
    public function provision(Group $group): array
    {
        $this->seedPermissions();

        return $this->withinGroup($group, function (): array {
            $owner = Role::findOrCreate('Owner', 'web');
            $member = Role::findOrCreate('Member', 'web');
            $owner->syncPermissions(self::OWNER_PERMISSIONS);
            $member->syncPermissions(self::MEMBER_PERMISSIONS);

            return ['owner' => $owner, 'member' => $member];
        });
    }

    public function assign(Actor $actor, Group $group, Role $role): void
    {
        $this->withinGroup($group, function () use ($actor, $role): void {
            $actor->syncRoles([$role]);
        });
    }

    public function hasRole(Actor $actor, Group $group, string $role): bool
    {
        return $this->withinGroup($group, fn (): bool => $actor->hasRole($role));
    }

    public function roleName(Actor $actor, Group $group): ?string
    {
        return $this->withinGroup($group, fn (): ?string => $actor->getRoleNames()->first());
    }

    /** @template T
     * @param callable(): T $callback
     * @return T
     */
    private function withinGroup(Group $group, callable $callback): mixed
    {
        $registrar = app(PermissionRegistrar::class);
        $currentGroupId = $registrar->getPermissionsTeamId();
        $registrar->setPermissionsTeamId($group->getKey());

        try {
            return $callback();
        } finally {
            $registrar->setPermissionsTeamId($currentGroupId);
        }
    }
}
