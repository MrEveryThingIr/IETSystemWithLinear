<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('relationships', function (Blueprint $table): void {
            $table->foreignId('matched_intent_id')
                ->nullable()
                ->after('originating_intent_id')
                ->constrained('actor_profile_intents')
                ->restrictOnDelete();

            $table->index('matched_intent_id', 'relationships_matched_intent_index');
        });
    }

    public function down(): void
    {
        Schema::table('relationships', function (Blueprint $table): void {
            $table->dropForeign(['matched_intent_id']);
            $table->dropIndex('relationships_matched_intent_index');
            $table->dropColumn('matched_intent_id');
        });
    }
};
