<?php

namespace App\Actions\Administration;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class GlobalAccess
{
    /** @var list<string> */
    private const PERMISSIONS = ['global.access', 'users.manage', 'groups.manage', 'actors.manage', 'rbac.manage'];

    public function seed(): void
    {
        foreach (self::PERMISSIONS as $permission) {
            DB::table('global_permissions')->upsert(['name' => $permission, 'created_at' => now(), 'updated_at' => now()], ['name'], ['updated_at']);
        }

        $roleId = DB::table('global_roles')->updateOrInsert(['name' => 'Global administrator'], ['updated_at' => now(), 'created_at' => now()])
            ? DB::table('global_roles')->where('name', 'Global administrator')->value('id')
            : null;

        $permissionIds = DB::table('global_permissions')->whereIn('name', self::PERMISSIONS)->pluck('id');
        foreach ($permissionIds as $permissionId) {
            DB::table('global_role_permissions')->updateOrInsert(['global_role_id' => $roleId, 'global_permission_id' => $permissionId]);
        }
    }

    /** @return list<string> */
    public function permissionNames(): array
    {
        return self::PERMISSIONS;
    }

    public function can(User $user, string $permission): bool
    {
        if ($this->isFullAdministrator($user)) {
            return true;
        }

        return DB::table('global_user_permissions')
            ->join('global_permissions', 'global_permissions.id', '=', 'global_user_permissions.global_permission_id')
            ->where('global_user_permissions.user_id', $user->id)
            ->where('global_permissions.name', $permission)
            ->exists();
    }

    public function isFullAdministrator(User $user): bool
    {
        return DB::table('global_user_roles')
            ->join('global_roles', 'global_roles.id', '=', 'global_user_roles.global_role_id')
            ->where('global_user_roles.user_id', $user->id)
            ->where('global_roles.name', 'Global administrator')
            ->exists();
    }

    /** @return list<string> */
    public function permissionsFor(User $user): array
    {
        if ($this->isFullAdministrator($user)) {
            return self::PERMISSIONS;
        }

        return DB::table('global_user_permissions')
            ->join('global_permissions', 'global_permissions.id', '=', 'global_user_permissions.global_permission_id')
            ->where('global_user_permissions.user_id', $user->id)
            ->pluck('global_permissions.name')
            ->all();
    }

    /** @param list<string> $permissions */
    public function sync(User $user, bool $fullAdministration, array $permissions): void
    {
        $this->seed();
        $permissions = array_values(array_intersect(self::PERMISSIONS, $permissions));

        DB::transaction(function () use ($user, $fullAdministration, $permissions): void {
            $administratorRoleId = DB::table('global_roles')->where('name', 'Global administrator')->value('id');
            if (! $fullAdministration && $this->isFullAdministrator($user) && $this->fullAdministratorCount() === 1) {
                abort(422, 'At least one global administrator must remain.');
            }

            DB::table('global_user_roles')->where('user_id', $user->id)->delete();
            DB::table('global_user_permissions')->where('user_id', $user->id)->delete();

            if ($fullAdministration) {
                DB::table('global_user_roles')->insert(['user_id' => $user->id, 'global_role_id' => $administratorRoleId]);

                return;
            }

            $permissionIds = DB::table('global_permissions')->whereIn('name', $permissions)->pluck('id');
            foreach ($permissionIds as $permissionId) {
                DB::table('global_user_permissions')->insert(['user_id' => $user->id, 'global_permission_id' => $permissionId]);
            }
        });
    }

    private function fullAdministratorCount(): int
    {
        return DB::table('global_user_roles')
            ->join('global_roles', 'global_roles.id', '=', 'global_user_roles.global_role_id')
            ->where('global_roles.name', 'Global administrator')
            ->count();
    }
}
