<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('feature_surface_settings')) {
            return;
        }

        DB::table('feature_surface_settings')->updateOrInsert(
            ['key' => 'enforcement_mode'],
            [
                'value' => 'strict',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        if (Schema::hasTable('feature_surface_settings')) {
            DB::table('feature_surface_settings')
                ->where('key', 'enforcement_mode')
                ->update([
                    'value' => 'observe',
                    'updated_at' => now(),
                ]);
        }
    }
};
