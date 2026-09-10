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
    private const OWNER_PERMISSIONS = ['manage_group', 'manage_members', 'manage_roles', 'manage_invitations', 'approve_role_changes', 'participate'];
    private const MEMBER_PERMISSIONS = ['participate'];

    public function __construct(private GroupOwnerIntegrity $ownerIntegrity) {}

    public static function permissionNames(): array { return array_values(array_unique([...self::OWNER_PERMISSIONS, ...self::MEMBER_PERMISSIONS])); }
    public function seedPermissions(): void { app(PermissionRegistrar::class)->forgetCachedPermissions(); foreach (self::permissionNames() as $permission) { Permission::findOrCreate($permission, 'web'); } }
    public function provision(Group $group): array { $this->seedPermissions(); return $this->withinGroup($group, function () use ($group): array { $owner = $this->findOrCreateRole($group, 'Owner'); $member = $this->findOrCreateRole($group, 'Member'); $owner->syncPermissions(self::OWNER_PERMISSIONS); $member->syncPermissions(self::MEMBER_PERMISSIONS); return compact('owner', 'member'); }); }
    public function roles(Group $group): Collection { return $this->withinGroup($group, fn (): Collection => Role::query()->where('group_id', $group->id)->with('permissions')->orderBy('name')->get()); }
    public function role(Group $group, int $roleId): Role { return $this->withinGroup($group, fn (): Role => Role::query()->where('group_id', $group->id)->with('permissions')->findOrFail($roleId)); }
    public function createRole(Group $group, string $name, array $permissions): Role { $this->seedPermissions(); return $this->withinGroup($group, function () use ($group, $name, $permissions): Role { $role = $this->findOrCreateRole($group, $name); $role->syncPermissions($permissions); return $role; }); }
    public function updateRole(Group $group, Role $role, string $name, array $permissions): void { $this->withinGroup($group, function () use ($role, $name, $permissions): void { $this->assertManagedRole($role); $role->update(['name' => $name]); $role->syncPermissions($permissions); }); }
    public function deleteRole(Group $group, Role $role): void { $this->withinGroup($group, function () use ($role): void { $this->assertManagedRole($role); abort_if($role->users()->exists(), 422, 'Reassign members before deleting a role.'); $role->delete(); }); }
    public function assign(Actor $actor, Group $group, Role $role): void { $this->ownerIntegrity->execute($group, function () use ($actor, $group, $role): void { $this->withinGroup($group, function () use ($actor, $group, $role): void { abort_unless((int) $role->getAttribute('group_id') === (int) $group->id, 422); $actor->syncRoles([$role]); }); }); }
    public function clear(Actor $actor, Group $group): void { $this->withinGroup($group, fn () => $actor->syncRoles([])); }
    public function hasRole(Actor $actor, Group $group, string $role): bool { return $this->withinGroup($group, function () use ($actor, $role): bool { $this->forget($actor); return $actor->hasRole($role); }); }
    public function hasPermission(Actor $actor, Group $group, string $permission): bool { return $this->withinGroup($group, function () use ($actor, $permission): bool { $this->forget($actor); return $actor->hasPermissionTo($permission); }); }
    public function roleName(Actor $actor, Group $group): ?string { return $this->withinGroup($group, function () use ($actor): ?string { $this->forget($actor); return $actor->getRoleNames()->first(); }); }
    private function forget(Actor $actor): void { $actor->unsetRelation('roles'); $actor->unsetRelation('permissions'); }
    private function assertManagedRole(Role $role): void { abort_if(in_array($role->name, ['Owner', 'Member'], true), 422, 'Built-in roles cannot be changed.'); }
    private function findOrCreateRole(Group $group, string $name): Role { Role::findOrCreate($name, 'web'); return Role::query()->where('group_id', $group->id)->where('name', $name)->where('guard_name', 'web')->firstOrFail(); }
    private function withinGroup(Group $group, callable $callback): mixed { $registrar = app(PermissionRegistrar::class); $current = $registrar->getPermissionsTeamId(); $registrar->setPermissionsTeamId($group->getKey()); try { return $callback(); } finally { $registrar->setPermissionsTeamId($current); } }
}
