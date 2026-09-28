<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'date_display_format')) {
                $table->string('date_display_format', 16)->default('long')->after('calendar');
            }

            if (! Schema::hasColumn('users', 'time_display_format')) {
                $table->string('time_display_format', 16)->default('24h')->after('date_display_format');
            }

            if (! Schema::hasColumn('users', 'show_gregorian_equivalent')) {
                $table->boolean('show_gregorian_equivalent')->default(true)->after('time_display_format');
            }
        });
    }

    public function down(): void
    {
        $columns = [];

        foreach (['date_display_format', 'time_display_format', 'show_gregorian_equivalent'] as $column) {
            if (Schema::hasColumn('users', $column)) {
                $columns[] = $column;
            }
        }

        if ($columns !== []) {
            Schema::table('users', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }
};
