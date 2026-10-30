<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A plain link from a Plan (optionally one of its occurrences) to a piece of Content. It is only a pointer: it grants
 * no reading rights to either side and changes neither the Plan nor the Content. Plan and Content visibility stay
 * independent, so a Content Placement (which grants reading through a Context) is deliberately not used for this.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_content_links', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
            $table->foreignId('plan_occurrence_id')->nullable()->constrained('plan_occurrences')->cascadeOnDelete();
            $table->foreignId('space_content_id')->constrained('space_contents')->cascadeOnDelete();
            $table->foreignId('linked_by_actor_id')->constrained('actors')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['plan_id', 'plan_occurrence_id', 'space_content_id'], 'plan_content_links_unique');
            $table->index('space_content_id');
            $table->index(['plan_id', 'plan_occurrence_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_content_links');
    }
};
