<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('feature_surface_grants')) {
            return;
        }

        DB::transaction(function (): void {
            $legacy = DB::table('feature_surface_grants')
                ->where('surface_key', 'real-estate')
                ->get();

            foreach ($legacy as $grant) {
                $businessGrantExists = DB::table('feature_surface_grants')
                    ->where('user_id', $grant->user_id)
                    ->where('surface_key', 'business')
                    ->exists();

                if (! $businessGrantExists) {
                    DB::table('feature_surface_grants')->insert([
                        'user_id' => $grant->user_id,
                        'surface_key' => 'business',
                        'granted_by_user_id' => $grant->granted_by_user_id,
                        'granted_at' => $grant->granted_at,
                        'created_at' => $grant->created_at,
                        'updated_at' => now(),
                    ]);
                }
            }

            DB::table('feature_surface_grants')
                ->where('surface_key', 'real-estate')
                ->delete();
        });
    }

    public function down(): void
    {
        // Deliberately irreversible: Real Estate is now a Business vertical.
    }
};
