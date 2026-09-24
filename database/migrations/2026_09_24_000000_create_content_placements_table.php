<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_placements', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('context_id')->constrained('contexts')->restrictOnDelete();
            $table->foreignId('space_content_id')->constrained('space_contents')->restrictOnDelete();
            $table->foreignId('placed_by_actor_id')->constrained('actors')->restrictOnDelete();
            $table->string('status', 16)->default('active');
            $table->timestamp('removed_at')->nullable();
            $table->foreignId('removed_by_actor_id')->nullable()->constrained('actors')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['context_id', 'space_content_id'], 'content_placements_context_content_uq');
            $table->index(['context_id', 'status'], 'content_placements_context_status_ix');
            $table->index(['space_content_id', 'status'], 'content_placements_content_status_ix');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_placements');
    }
};
