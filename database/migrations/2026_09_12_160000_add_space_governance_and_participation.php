<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('group_spaces', function (Blueprint $table): void {
            $table->string('access_mode', 20)->default('group')->after('kind');
            $table->index(
                ['group_id', 'status', 'access_mode'],
                'group_spaces_group_status_access_mode_index',
            );
        });

        DB::table('group_spaces')
            ->where(function ($query): void {
                $query->where('is_default', true)->orWhere('slug', 'general');
            })
            ->update(['access_mode' => 'group']);

        Schema::create('group_space_participants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('group_space_id')->constrained('group_spaces')->cascadeOnDelete();
            $table->foreignId('actor_id')->constrained('actors')->restrictOnDelete();
            $table->string('access', 20);
            $table->string('role', 20)->default('participant');
            $table->foreignId('granted_by_actor_id')->constrained('actors')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['group_space_id', 'actor_id']);
            $table->index(['actor_id', 'access']);
            $table->index(
                ['group_space_id', 'access', 'role'],
                'group_space_participants_space_access_role_index',
            );
        });

        $this->backfillManageSpacesPermission();
    }

    public function down(): void
    {
        $this->removeManageSpacesPermission();

        Schema::dropIfExists('group_space_participants');

        Schema::table('group_spaces', function (Blueprint $table): void {
            $table->dropIndex('group_spaces_group_status_access_mode_index');
            $table->dropColumn('access_mode');
        });
    }

    private function backfillManageSpacesPermission(): void
    {
        $tableNames = config('permission.table_names');
        throw_if(empty($tableNames), 'Permission table configuration is unavailable.');

        $permissionId = DB::table($tableNames['permissions'])
            ->where('name', 'manage_spaces')
            ->where('guard_name', 'web')
            ->value('id');

        if ($permissionId === null) {
            $permissionId = DB::table($tableNames['permissions'])->insertGetId([
                'name' => 'manage_spaces',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $ownerRoleIds = DB::table($tableNames['roles'])
            ->where('system_key', 'owner')
            ->pluck('id');

        foreach ($ownerRoleIds as $roleId) {
            DB::table($tableNames['role_has_permissions'])->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $roleId,
            ]);
        }

        $this->forgetPermissionCache();
    }

    private function removeManageSpacesPermission(): void
    {
        $tableNames = config('permission.table_names');

        if (empty($tableNames)) {
            return;
        }

        DB::table($tableNames['permissions'])
            ->where('name', 'manage_spaces')
            ->where('guard_name', 'web')
            ->delete();

        $this->forgetPermissionCache();
    }

    private function forgetPermissionCache(): void
    {
        app('cache')
            ->store(config('permission.cache.store') !== 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }
};
