<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('public_real_estate_cases', function (Blueprint $table): void {
            $table->unsignedSmallInteger('building_age_years')->nullable()->after('built_year_calendar');
        });

        Schema::create('public_intake_portal_grants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('public_intake_portal_id')->constrained('public_intake_portals')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20)->default('viewer'); // viewer | manager
            $table->timestamps();
            $table->unique(['public_intake_portal_id', 'user_id'], 'public_intake_grants_portal_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_intake_portal_grants');
        Schema::table('public_real_estate_cases', fn (Blueprint $table) => $table->dropColumn('building_age_years'));
    }
};
