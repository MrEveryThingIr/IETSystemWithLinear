<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('concept_assertions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('subject_type', 64);
            $table->unsignedBigInteger('subject_id');
            $table->foreignId('concept_id')->constrained('concepts')->restrictOnDelete();
            $table->string('predicate', 64);
            $table->foreignId('scheme_id')->nullable()->constrained('concept_schemes')->restrictOnDelete();
            $table->decimal('weight', 8, 4)->nullable();
            $table->decimal('confidence', 5, 4)->nullable();
            $table->string('source', 32)->default('manual');
            $table->string('visibility', 32)->default('inherited');
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->foreignId('created_by_actor_id')->nullable()->constrained('actors')->restrictOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(
                ['subject_type', 'subject_id', 'concept_id', 'predicate'],
                'concept_assertions_semantic_unique',
            );
            $table->index(
                ['subject_type', 'subject_id', 'predicate'],
                'concept_assertions_subject_predicate_index',
            );
            $table->index(['concept_id', 'predicate'], 'concept_assertions_concept_predicate_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('concept_assertions');
    }
};
