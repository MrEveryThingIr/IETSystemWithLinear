<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('actor_profile_intent_media', fn (Blueprint $table) => $table->softDeletes());
    }

    public function down(): void
    {
        Schema::table('actor_profile_intent_media', fn (Blueprint $table) => $table->dropSoftDeletes());
    }
};
