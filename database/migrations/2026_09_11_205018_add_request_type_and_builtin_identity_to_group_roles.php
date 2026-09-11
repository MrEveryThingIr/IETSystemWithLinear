<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('group_role_change_requests')
            ->where('status', 'pending')
            ->orderByDesc('id')
            ->get()
            ->groupBy(fn (object $request): string => implode(':', [$request->membership_id, $request->requested_role_id]))
            ->each(function ($requests): void {
                $duplicateIds = $requests->skip(1)->pluck('id');

                if ($duplicateIds->isNotEmpty()) {
                    DB::table('group_role_change_requests')->whereIn('id', $duplicateIds)->update(['status' => 'cancelled']);
                }
            });

        Schema::table('roles', function (Blueprint $table): void {
            $table->string('system_key', 40)->nullable()->after('group_id');
            $table->unique(['group_id', 'system_key']);
        });

        DB::table('roles')->whereNotNull('group_id')->whereRaw('LOWER(name) = ?', ['owner'])->update(['system_key' => 'owner']);
        DB::table('roles')->whereNotNull('group_id')->whereRaw('LOWER(name) = ?', ['member'])->update(['system_key' => 'member']);

        $permissionNames = [
            'participate',
            'manage_group',
            'manage_members',
            'manage_roles',
            'manage_invitations',
            'approve_role_changes',
            'manage_admissions',
            'manage_agreements',
            'view_group_audit',
            'manage_simulations',
            'transfer_ownership',
        ];

        foreach ($permissionNames as $permissionName) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $permissionName, 'guard_name' => 'web'],
                ['updated_at' => now(), 'created_at' => now()],
            );
        }

        $permissionIds = DB::table('permissions')->where('guard_name', 'web')->whereIn('name', $permissionNames)->pluck('id', 'name');
        $ownerRoleIds = DB::table('roles')->where('system_key', 'owner')->pluck('id');
        $memberRoleIds = DB::table('roles')->where('system_key', 'member')->pluck('id');

        foreach ($ownerRoleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $roleId]);
            }
        }

        foreach ($memberRoleIds as $roleId) {
            DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $permissionIds['participate'], 'role_id' => $roleId]);
        }

        Schema::table('group_role_change_requests', function (Blueprint $table): void {
            $table->string('request_type', 10)->default('grant')->after('requested_role_id');
            $table->string('pending_key')->nullable()->after('request_type')->unique();
            $table->index(['membership_id', 'requested_role_id', 'request_type', 'status'], 'group_role_requests_pending_lookup_index');
        });

        DB::table('group_role_change_requests')
            ->where('status', 'pending')
            ->orderBy('id')
            ->each(function (object $request): void {
                DB::table('group_role_change_requests')->where('id', $request->id)->update([
                    'pending_key' => implode(':', [$request->membership_id, $request->requested_role_id, 'grant']),
                ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('group_role_change_requests', function (Blueprint $table): void {
            $table->dropIndex('group_role_requests_pending_lookup_index');
            $table->dropUnique(['pending_key']);
            $table->dropColumn(['request_type', 'pending_key']);
        });

        Schema::table('roles', function (Blueprint $table): void {
            $table->dropUnique(['group_id', 'system_key']);
            $table->dropColumn('system_key');
        });
    }
};
