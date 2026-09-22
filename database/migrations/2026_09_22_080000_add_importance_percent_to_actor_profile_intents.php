<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('actor_profile_intents', function (Blueprint $table): void {
            $table->unsignedTinyInteger('importance_percent')
                ->nullable()
                ->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('actor_profile_intents', function (Blueprint $table): void {
            $table->dropColumn('importance_percent');
        });
    }
};
