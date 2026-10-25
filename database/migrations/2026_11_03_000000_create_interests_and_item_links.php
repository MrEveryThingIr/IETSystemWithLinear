<?php

use App\Actions\Items\ConsolidateLegacyProfileItems;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M1 — one home for me.
 *
 * Interests become their own private record (one per person and category,
 * with purposes). Old profile tags ("has skill", "interested in", "wants to
 * learn") are copied into Abilities and Interests. Nothing is deleted: every
 * copy is remembered in legacy_item_links so the copy never runs twice, and
 * the old rows stay where they were.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('interests')) {
            Schema::create('interests', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique('interests_uuid_uq');
                $table->foreignId('holder_actor_id')->constrained('actors')->restrictOnDelete();
                $table->foreignId('concept_id')->constrained('concepts')->restrictOnDelete();
                $table->json('purposes');
                $table->string('note', 500)->nullable();
                $table->string('visibility', 16)->default('private');
                $table->foreignId('created_by_actor_id')->constrained('actors')->restrictOnDelete();
                $table->timestamps();

                $table->unique(['holder_actor_id', 'concept_id'], 'interests_holder_concept_uq');
                $table->index(['concept_id', 'visibility'], 'interests_concept_visibility_ix');
            });
        }

        if (! Schema::hasTable('legacy_item_links')) {
            Schema::create('legacy_item_links', function (Blueprint $table): void {
                $table->id();
                $table->string('source_type', 40);
                $table->unsignedBigInteger('source_id');
                $table->string('target_type', 40);
                $table->unsignedBigInteger('target_id');
                $table->dateTime('created_at')->nullable();

                $table->unique(['source_type', 'source_id'], 'legacy_item_links_source_uq');
                $table->index(['target_type', 'target_id'], 'legacy_item_links_target_ix');
            });
        }

        app(ConsolidateLegacyProfileItems::class)->execute();
    }

    public function down(): void
    {
        // The copies are ordinary records people may have edited since; the
        // old tags were never changed, so dropping the new tables restores
        // the previous state exactly.
        Schema::dropIfExists('legacy_item_links');
        Schema::dropIfExists('interests');
    }
};
