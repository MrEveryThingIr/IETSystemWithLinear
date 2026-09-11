<?php

namespace App\Actions\Groups;

use App\GroupPermission;
use App\GroupRoleKey;
use App\Models\Actor;
use App\Models\Group;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class GroupRoleProvisioner
{
    public function __construct(private GroupOwnerIntegrity $ownerIntegrity) {}

    /** @return list<string> */
    public static function permissionNames(): array
    {
        return GroupPermission::values();
    }

    /** @return list<string> */
    public static function customRolePermissionNames(): array
    {
        return array_values(array_filter(
            self::permissionNames(),
            fn (string $permission): bool => $permission !== GroupPermission::TransferOwnership->value,
        ));
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

        return $this->withinGroup($group, function () use ($group): array {
            $owner = $this->findOrCreateBuiltInRole($group, GroupRoleKey::Owner);
            $member = $this->findOrCreateBuiltInRole($group, GroupRoleKey::Member);
            $owner->syncPermissions(self::permissionNames());
            $member->syncPermissions([GroupPermission::Participate->value]);

            return compact('owner', 'member');
        });
    }

    /** @return Collection<int, Role> */
    public function roles(Group $group): Collection
    {
        return $this->withinGroup($group, fn (): Collection => Role::query()
            ->where('group_id', $group->id)
            ->with('permissions')
            ->orderByRaw('CASE WHEN system_key = ? THEN 0 WHEN system_key = ? THEN 1 ELSE 2 END', [GroupRoleKey::Owner->value, GroupRoleKey::Member->value])
            ->orderBy('name')
            ->get());
    }

    public function role(Group $group, int $roleId): Role
    {
        return $this->withinGroup($group, fn (): Role => Role::query()
            ->where('group_id', $group->id)
            ->with('permissions')
            ->findOrFail($roleId));
    }

    public function builtInRole(Group $group, GroupRoleKey $key): Role
    {
        return $this->withinGroup($group, fn (): Role => Role::query()
            ->where('group_id', $group->id)
            ->where('system_key', $key->value)
            ->firstOrFail());
    }

    /** @param list<string> $permissions */
    public function createRole(Group $group, string $name, array $permissions): Role
    {
        $this->seedPermissions();

        return $this->withinGroup($group, function () use ($group, $name, $permissions): Role {
            $normalizedName = trim($name);
            $this->assertCustomRoleName($group, $normalizedName);
            $this->assertCustomPermissions($permissions);

            $role = new Role;
            $role->name = $normalizedName;
            $role->guard_name = 'web';
            $role->setAttribute('group_id', $group->id);
            $role->save();
            $role->syncPermissions($permissions);

            return $role;
        });
    }

    /** @param list<string> $permissions */
    public function updateRole(Group $group, Role $role, string $name, array $permissions): void
    {
        $this->withinGroup($group, function () use ($group, $role, $name, $permissions): void {
            $this->assertCustomRole($role);
            $normalizedName = trim($name);
            $this->assertCustomRoleName($group, $normalizedName, $role->id);
            $this->assertCustomPermissions($permissions);

            $role->name = $normalizedName;
            $role->save();
            $role->syncPermissions($permissions);
        });
    }

    public function deleteRole(Group $group, Role $role): void
    {
        $this->withinGroup($group, function () use ($role): void {
            $this->assertCustomRole($role);
            abort_if($role->users()->exists(), 422, 'Revoke this role from every member before deleting it.');
            $role->delete();
        });
    }

    public function grant(Actor $actor, Group $group, Role $role): void
    {
        DB::transaction(function () use ($actor, $group, $role): void {
            $this->assertActiveMembership($actor, $group);

            $this->withinGroup($group, function () use ($actor, $group, $role): void {
                $this->assertRoleBelongsToGroup($role, $group);
                $memberRole = $this->builtInRole($group, GroupRoleKey::Member);
                $actor->assignRole($memberRole);
                $actor->assignRole($role);
                $this->forget($actor);
            });
        }, attempts: 3);
    }

    /** @deprecated Use grant() to make additive semantics explicit. */
    public function assign(Actor $actor, Group $group, Role $role): void
    {
        $this->grant($actor, $group, $role);
    }

    public function revoke(Actor $actor, Group $group, Role $role): void
    {
        $this->assertRoleBelongsToGroup($role, $group);
        abort_if($role->getAttribute('system_key') === GroupRoleKey::Member->value, 422, 'The baseline Member role cannot be revoked from an active membership.');

        $mutation = function () use ($actor, $group, $role): void {
            $this->withinGroup($group, function () use ($actor, $role): void {
                $actor->removeRole($role);
                $this->forget($actor);
            });
        };

        if ($role->getAttribute('system_key') === GroupRoleKey::Owner->value) {
            $this->ownerIntegrity->execute($group, $mutation);

            return;
        }

        DB::transaction($mutation, attempts: 3);
    }

    public function revokeNonBaselineRoles(Actor $actor, Group $group): void
    {
        $this->ownerIntegrity->execute($group, function () use ($actor, $group): void {
            $this->withinGroup($group, function () use ($actor, $group): void {
                $this->forget($actor);
                $roleIds = DB::table('model_has_roles')
                    ->where('model_type', $actor->getMorphClass())
                    ->where('model_id', $actor->id)
                    ->where('group_id', $group->id)
                    ->pluck('role_id');
                $assignedRoles = Role::query()->whereIn('id', $roleIds)->get();

                foreach ($assignedRoles as $role) {
                    if ($role->getAttribute('system_key') !== GroupRoleKey::Member->value) {
                        $actor->removeRole($role);
                    }
                }

                $this->forget($actor);
            });
        });
    }

    public function hasRole(Actor $actor, Group $group, string $role): bool
    {
        return $this->withinGroup($group, function () use ($actor, $role): bool {
            $this->forget($actor);

            return $actor->hasRole($role);
        });
    }

    public function hasBuiltInRole(Actor $actor, Group $group, GroupRoleKey $role): bool
    {
        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', $actor->getMorphClass())
            ->where('model_has_roles.model_id', $actor->id)
            ->where('model_has_roles.group_id', $group->id)
            ->where('roles.group_id', $group->id)
            ->where('roles.system_key', $role->value)
            ->exists();
    }

    public function hasPermission(Actor $actor, Group $group, string $permission): bool
    {
        return $this->withinGroup($group, function () use ($actor, $permission): bool {
            $this->forget($actor);

            return $actor->hasPermissionTo($permission);
        });
    }

    /** @return Collection<int, string> */
    public function roleNames(Actor $actor, Group $group): Collection
    {
        return $this->withinGroup($group, function () use ($actor): Collection {
            $this->forget($actor);

            return $actor->getRoleNames()->sort()->values();
        });
    }

    /** @deprecated Render roleNames() because contextual roles are additive. */
    public function roleName(Actor $actor, Group $group): ?string
    {
        return $this->roleNames($actor, $group)->first();
    }

    private function assertActiveMembership(Actor $actor, Group $group): void
    {
        abort_unless($group->memberships()->where('actor_id', $actor->id)->where('status', 'active')->exists(), 422, 'Roles require an active Group membership.');
    }

    private function assertRoleBelongsToGroup(Role $role, Group $group): void
    {
        abort_unless((int) $role->getAttribute('group_id') === (int) $group->id, 422, 'The role does not belong to this Group.');
    }

    private function assertCustomRole(Role $role): void
    {
        abort_if($role->getAttribute('system_key') !== null, 422, 'Built-in roles cannot be changed.');
    }

    private function assertCustomRoleName(Group $group, string $name, ?int $exceptRoleId = null): void
    {
        $normalizedName = Str::lower($name);
        abort_if(in_array($normalizedName, GroupRoleKey::values(), true), 422, 'Built-in role names are reserved.');

        $duplicate = Role::query()
            ->where('group_id', $group->id)
            ->whereRaw('LOWER(name) = ?', [$normalizedName])
            ->when($exceptRoleId !== null, fn ($query) => $query->whereKeyNot($exceptRoleId))
            ->exists();

        abort_if($duplicate, 422, 'A role with this name already exists.');
    }

    /** @param list<string> $permissions */
    private function assertCustomPermissions(array $permissions): void
    {
        $invalid = array_diff($permissions, self::customRolePermissionNames());
        abort_if($invalid !== [], 422, 'Custom roles cannot receive reserved or unknown permissions.');
    }

    private function findOrCreateBuiltInRole(Group $group, GroupRoleKey $key): Role
    {
        $role = Role::query()->where('group_id', $group->id)->where('system_key', $key->value)->first();

        if (! $role instanceof Role) {
            $role = Role::query()
                ->where('group_id', $group->id)
                ->whereRaw('LOWER(name) = ?', [Str::lower($key->label())])
                ->first();
        }

        if (! $role instanceof Role) {
            $role = new Role;
            $role->name = $key->label();
            $role->guard_name = 'web';
            $role->setAttribute('group_id', $group->id);
        }

        $role->setAttribute('system_key', $key->value);
        $role->save();

        return $role;
    }

    private function forget(Actor $actor): void
    {
        $actor->unsetRelation('roles');
        $actor->unsetRelation('permissions');
    }

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
