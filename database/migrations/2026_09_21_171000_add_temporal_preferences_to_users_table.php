<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'timezone')) {
                $table->string('timezone', 64)->nullable()->after('locale');
            }

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
        $columns = [];

        if (Schema::hasColumn('users', 'calendar')) {
            $columns[] = 'calendar';
        }

        if (Schema::hasColumn('users', 'timezone_mode')) {
            $columns[] = 'timezone_mode';
        }

        if ($columns !== []) {
            Schema::table('users', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }
};
