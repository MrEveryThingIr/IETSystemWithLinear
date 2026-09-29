<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_assistance_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('context_id')->constrained('contexts')->restrictOnDelete();
            $table->foreignId('space_content_id')->constrained('space_contents')->restrictOnDelete();
            $table->foreignId('base_revision_id')->constrained('space_content_revisions')->restrictOnDelete();
            $table->foreignId('requested_by_actor_id')->constrained('actors')->restrictOnDelete();
            $table->string('status', 20);
            $table->string('provider', 40);
            $table->string('model', 120);
            $table->string('external_response_id', 160)->nullable();
            $table->text('prompt');
            $table->char('request_hash', 64);
            $table->json('proposal')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('planned_at')->nullable();
            $table->foreignId('applied_revision_id')->nullable()->constrained('space_content_revisions')->restrictOnDelete();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['space_content_id', 'status', 'id']);
            $table->index(['requested_by_actor_id', 'status', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_assistance_runs');
    }
};
