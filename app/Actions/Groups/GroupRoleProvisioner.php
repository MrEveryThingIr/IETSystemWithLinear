<?php

namespace App\Actions\Groups;

use App\Models\Actor;
use App\Models\Group;
use Illuminate\Support\Collection;
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
            $owner = $this->findOrCreateRole('Owner');
            $member = $this->findOrCreateRole('Member');
            $owner->syncPermissions(self::OWNER_PERMISSIONS);
            $member->syncPermissions(self::MEMBER_PERMISSIONS);

            return ['owner' => $owner, 'member' => $member];
        });
    }

    /** @return Collection<int, Role> */
    public function roles(Group $group): Collection
    {
        return $this->withinGroup($group, fn (): Collection => Role::query()->where('group_id', $group->id)->with('permissions')->orderBy('name')->get());
    }

    public function role(Group $group, int $roleId): Role
    {
        return $this->withinGroup($group, fn (): Role => Role::query()->where('group_id', $group->id)->with('permissions')->findOrFail($roleId));
    }

    public function createRole(Group $group, string $name, array $permissions): Role
    {
        $this->seedPermissions();

        return $this->withinGroup($group, function () use ($name, $permissions): Role {
            $role = Role::create(['name' => $name, 'guard_name' => 'web']);

            if (! $role instanceof Role) {
                throw new \LogicException('The configured permission role model must extend '.Role::class.'.');
            }

            $role->syncPermissions($permissions);

            return $role;
        });
    }

    public function updateRole(Group $group, Role $role, string $name, array $permissions): void
    {
        $this->withinGroup($group, function () use ($role, $name, $permissions): void {
            $this->assertManagedRole($role);
            $role->update(['name' => $name]);
            $role->syncPermissions($permissions);
        });
    }

    public function deleteRole(Group $group, Role $role): void
    {
        $this->withinGroup($group, function () use ($role): void {
            $this->assertManagedRole($role);
            $role->delete();
        });
    }

    public function assign(Actor $actor, Group $group, Role $role): void
    {
        $this->withinGroup($group, function () use ($actor, $group, $role): void {
            abort_unless((int) $role->getAttribute('group_id') === (int) $group->id, 422);
            $actor->syncRoles([$role]);
        });
    }

    public function hasRole(Actor $actor, Group $group, string $role): bool
    {
        return $this->withinGroup($group, function () use ($actor, $role): bool {
            $this->forgetLoadedAuthorizationRelations($actor);

            return $actor->hasRole($role);
        });
    }

    public function hasPermission(Actor $actor, Group $group, string $permission): bool
    {
        return $this->withinGroup($group, function () use ($actor, $permission): bool {
            $this->forgetLoadedAuthorizationRelations($actor);

            return $actor->hasPermissionTo($permission);
        });
    }

    public function roleName(Actor $actor, Group $group): ?string
    {
        return $this->withinGroup($group, function () use ($actor): ?string {
            $this->forgetLoadedAuthorizationRelations($actor);

            return $actor->getRoleNames()->first();
        });
    }

    private function forgetLoadedAuthorizationRelations(Actor $actor): void
    {
        $actor->unsetRelation('roles');
        $actor->unsetRelation('permissions');
    }

    private function assertManagedRole(Role $role): void
    {
        abort_if(in_array($role->name, ['Owner', 'Member'], true), 422, 'Built-in roles cannot be changed.');
    }

    private function findOrCreateRole(string $name): Role
    {
        $role = Role::findOrCreate($name, 'web');

        if (! $role instanceof Role) {
            throw new \LogicException('The configured permission role model must extend '.Role::class.'.');
        }

        return $role;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
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
