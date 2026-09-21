<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'timezone_mode')) {
                $table->string('timezone_mode', 16)->default('auto')->after('timezone');
            }

            if (! Schema::hasColumn('users', 'calendar')) {
                $table->string('calendar', 32)->nullable()->after('timezone_mode');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'calendar')) {
                $table->dropColumn('calendar');
            }

            if (Schema::hasColumn('users', 'timezone_mode')) {
                $table->dropColumn('timezone_mode');
            }
        });
    }
};
