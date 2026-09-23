<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reference_contexts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('context_id')->unique('reference_context_context_uq');
            $table->string('key', 120)->unique('reference_context_key_uq');
            $table->string('audience', 32)->default('authenticated');
            $table->foreignId('managed_by_actor_id');
            $table->timestamps();

            $table->foreign('context_id', 'reference_context_context_fk')
                ->references('id')
                ->on('contexts')
                ->restrictOnDelete();
            $table->foreign('managed_by_actor_id', 'reference_context_manager_fk')
                ->references('id')
                ->on('actors')
                ->restrictOnDelete();
            $table->index(['audience', 'managed_by_actor_id'], 'reference_context_access_ix');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reference_contexts');
    }
};
