<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Approved, versioned shared field definitions. Code-owned keys stay in IntentDetailCatalog;
        // a row here either adds a new key or overlays labels, aliases and options of a code key.
        Schema::create('intent_field_definitions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('field_key', 64);
            $table->unsignedInteger('version');
            $table->string('status', 16)->default('published');
            $table->string('type', 32);
            $table->string('filter', 24)->nullable();
            $table->string('unit', 64)->nullable();
            $table->json('options');
            $table->json('domains');
            $table->json('labels');
            $table->json('descriptions');
            $table->json('aliases');
            $table->foreignId('proposal_id')->nullable();
            $table->foreignId('published_by_actor_id')->nullable()->constrained('actors')->restrictOnDelete();
            $table->dateTime('published_at');
            $table->dateTime('retired_at')->nullable();
            $table->unique(['field_key', 'version'], 'intent_field_key_version_unique');
            $table->index(['status', 'field_key'], 'intent_field_status_idx');
        });
        Schema::create('intent_field_proposals', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('proposed_by_actor_id')->constrained('actors')->restrictOnDelete();
            $table->string('status', 16)->default('proposed');
            $table->string('kind', 16)->default('new');
            $table->string('target_key', 64)->nullable();
            $table->json('payload');
            $table->string('duplicate_of', 64)->nullable();
            $table->foreignId('reviewer_actor_id')->nullable()->constrained('actors')->restrictOnDelete();
            $table->text('review_note')->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->foreignId('definition_id')->nullable()->constrained('intent_field_definitions')->restrictOnDelete();
            $table->timestamps();
            $table->index(['status', 'id'], 'intent_field_proposal_status_idx');
        });
        Schema::table('intent_detail_templates', function (Blueprint $table): void {
            // composition = whole structure, group = a few fields, field = one personal field definition.
            $table->string('kind', 16)->default('composition');
            $table->string('topic', 24)->nullable();
            $table->json('terms')->nullable();
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('intent_detail_templates', 'kind')) {
            Schema::table('intent_detail_templates', fn (Blueprint $table) => $table->dropColumn(['kind', 'topic', 'terms']));
        }
        Schema::dropIfExists('intent_field_proposals');
        Schema::dropIfExists('intent_field_definitions');
    }
};
