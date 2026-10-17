<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Replace the boolean Gregorian-equivalent preference with a presentation mode.
 *
 *   hidden → never shown
 *   hover  → shown on hover / focus / tap (new default)
 *   inline → permanent second line (previous "on" behaviour)
 *
 * Existing users: false → hidden, true → hover. The boolean column is kept and
 * kept in sync so a rollback or an older release keeps working.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'gregorian_equivalent_mode')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('gregorian_equivalent_mode', 8)->default('hover')->after('show_gregorian_equivalent');
            });
        }

        DB::table('users')->where('show_gregorian_equivalent', false)->update(['gregorian_equivalent_mode' => 'hidden']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'gregorian_equivalent_mode')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('gregorian_equivalent_mode');
            });
        }
    }
};
