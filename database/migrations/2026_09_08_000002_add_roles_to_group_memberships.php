<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_roles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->json('permissions');
            $table->timestamps();
            $table->unique(['group_id', 'name']);
        });

        Schema::table('group_memberships', function (Blueprint $table): void {
            $table->foreignId('group_role_id')->nullable()->after('actor_id')->constrained()->nullOnDelete();
        });

        Schema::create('group_role_change_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('group_membership_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_group_role_id')->constrained('group_roles')->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->string('status')->default('pending');
            $table->foreignId('reviewed_by_actor_id')->nullable()->constrained('actors')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        DB::table('groups')->orderBy('id')->each(function (object $group): void {
            $ownerRole = DB::table('group_roles')->insertGetId([
                'group_id' => $group->id,
                'name' => 'Owner',
                'permissions' => json_encode(['manage_group', 'manage_members', 'manage_roles', 'manage_invitations', 'approve_role_changes']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $memberRole = DB::table('group_roles')->insertGetId([
                'group_id' => $group->id,
                'name' => 'Member',
                'permissions' => json_encode(['participate']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('group_memberships')->where('group_id', $group->id)->update([
                'group_role_id' => DB::raw("CASE WHEN role = 'owner' THEN {$ownerRole} ELSE {$memberRole} END"),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_role_change_requests');
        Schema::table('group_memberships', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('group_role_id');
        });
        Schema::dropIfExists('group_roles');
    }
};
