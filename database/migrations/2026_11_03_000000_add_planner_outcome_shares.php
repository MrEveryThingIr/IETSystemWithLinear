<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planner_outcome_shares', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('subject_actor_id')->constrained('actors')->restrictOnDelete();
            $table->foreignId('recipient_actor_id')->constrained('actors')->restrictOnDelete();
            $table->string('purpose', 500);
            $table->json('plan_ids');
            $table->json('snapshot');
            $table->dateTime('expires_at');
            $table->dateTime('revoked_at')->nullable();
            $table->dateTime('created_at');
            $table->index(['subject_actor_id', 'recipient_actor_id'], 'planner_share_parties_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planner_outcome_shares');
    }
};
