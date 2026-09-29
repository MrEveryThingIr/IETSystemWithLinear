<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plan_schedule_rules', function (Blueprint $table): void {
            $table->string('timing_mode', 24)->default('fixed')->after('frequency');
        });
    }

    public function down(): void
    {
        Schema::table('plan_schedule_rules', function (Blueprint $table): void {
            $table->dropColumn('timing_mode');
        });
    }
};
