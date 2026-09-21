<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('concept_vocabularies', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('scope_type', 32);
            $table->unsignedBigInteger('scope_id')->default(0);
            $table->string('name');
            $table->string('slug');
            $table->string('status', 32)->default('active');
            $table->foreignId('created_by_actor_id')->constrained('actors')->restrictOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['scope_type', 'scope_id', 'slug'], 'concept_vocabularies_scope_slug_unique');
            $table->index(['scope_type', 'scope_id'], 'concept_vocabularies_scope_index');
        });

        Schema::create('concepts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('vocabulary_id')->constrained('concept_vocabularies')->restrictOnDelete();
            $table->string('slug');
            $table->string('status', 32)->default('active');
            $table->unsignedBigInteger('merged_into_concept_id')->nullable();
            $table->text('summary')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['vocabulary_id', 'slug']);
            $table->index(['vocabulary_id', 'status']);
        });

        Schema::table('concepts', function (Blueprint $table): void {
            $table->foreign('merged_into_concept_id')
                ->references('id')
                ->on('concepts')
                ->restrictOnDelete();
        });

        Schema::create('concept_labels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('concept_id')->constrained('concepts')->cascadeOnDelete();
            $table->string('locale', 16);
            $table->string('label');
            $table->string('kind', 32);
            $table->string('normalized_label');
            $table->timestamps();

            $table->unique(
                ['concept_id', 'locale', 'kind', 'normalized_label'],
                'concept_labels_identity_unique',
            );
            $table->index(['locale', 'normalized_label']);
        });

        Schema::create('concept_schemes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('vocabulary_id')->constrained('concept_vocabularies')->restrictOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('status', 32)->default('active');
            $table->foreignId('created_by_actor_id')->constrained('actors')->restrictOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['vocabulary_id', 'slug']);
        });

        Schema::create('concept_scheme_memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('scheme_id')->constrained('concept_schemes')->cascadeOnDelete();
            $table->foreignId('concept_id')->constrained('concepts')->restrictOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['scheme_id', 'concept_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('concept_scheme_memberships');
        Schema::dropIfExists('concept_schemes');
        Schema::dropIfExists('concept_labels');
        Schema::dropIfExists('concepts');
        Schema::dropIfExists('concept_vocabularies');
    }
};
