<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('platform_super_admins')) {
            return;
        }

        DB::transaction(function (): void {
            $legacyAdmins = DB::table('platform_super_admins')->orderBy('id')->get();

            foreach ($legacyAdmins as $legacyAdmin) {
                $alreadyGranted = DB::table('platform_access_grants')
                    ->where('user_id', $legacyAdmin->user_id)
                    ->where('role', 'superadmin')
                    ->whereNull('revoked_at')
                    ->exists();

                if ($alreadyGranted) {
                    continue;
                }

                DB::table('platform_access_grants')->insert([
                    'user_id' => $legacyAdmin->user_id,
                    'role' => 'superadmin',
                    'granted_by_user_id' => $legacyAdmin->granted_by_user_id,
                    'granted_at' => $legacyAdmin->granted_at ?? $legacyAdmin->created_at ?? now(),
                    'revoked_by_user_id' => null,
                    'revoked_at' => null,
                    'reason' => 'Consolidated from the draft platform_super_admins registry.',
                    'correlation_id' => (string) Str::uuid(),
                    'created_at' => $legacyAdmin->created_at ?? now(),
                    'updated_at' => now(),
                ]);
            }
        });

        Schema::drop('platform_super_admins');
    }

    public function down(): void
    {
        // Irreversible by design: platform_access_grants remains the authority.
    }
};
