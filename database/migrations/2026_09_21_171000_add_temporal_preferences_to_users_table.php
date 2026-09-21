<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'calendar')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('calendar', 32)->nullable()->after('timezone');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'calendar')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('calendar');
            });
        }
    }
};
