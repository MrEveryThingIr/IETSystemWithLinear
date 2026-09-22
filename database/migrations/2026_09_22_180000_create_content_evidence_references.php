<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_evidence_references', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('context_id')->constrained('contexts')->restrictOnDelete();
            $table->foreignId('space_content_id')->constrained('space_contents')->restrictOnDelete();
            $table->foreignId('space_content_revision_id')->constrained('space_content_revisions')->restrictOnDelete();
            $table->string('target_type', 24);
            $table->uuid('target_uuid')->nullable();
            $table->string('field_key', 64)->nullable();
            $table->foreignId('created_by_actor_id')->constrained('actors')->restrictOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['space_content_revision_id', 'target_type'], 'cer_revision_target_ix');
            $table->index(['context_id', 'created_at'], 'cer_context_created_ix');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_evidence_references');
    }
};
