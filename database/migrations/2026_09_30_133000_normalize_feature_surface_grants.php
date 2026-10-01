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

        $map = [
            'profile.contacts' => 'profile',
            'profile.professions' => 'profile',

            'business.core' => 'business',
            'business.contacts' => 'business',
            'business.team' => 'business',
            'business.professions' => 'business',

            'real-estate.office' => 'real-estate',
            'real-estate.media' => 'real-estate',

            'contracts' => 'deals',
            'finance' => 'accounting',
            'docs' => 'manual',
        ];

        DB::transaction(function () use ($map): void {
            foreach ($map as $old => $new) {
                $rows = DB::table('feature_surface_grants')
                    ->where('surface_key', $old)
                    ->get();

                foreach ($rows as $row) {
                    DB::table('feature_surface_grants')->updateOrInsert(
                        [
                            'user_id' => $row->user_id,
                            'surface_key' => $new,
                        ],
                        [
                            'granted_by_user_id' => $row->granted_by_user_id,
                            'granted_at' => $row->granted_at,
                            'created_at' => $row->created_at,
                            'updated_at' => now(),
                        ]
                    );
                }

                DB::table('feature_surface_grants')
                    ->where('surface_key', $old)
                    ->delete();
            }
        });
    }

    public function down(): void
    {
        // Deliberately irreversible: the old fragmented keys were transitional.
    }
};
