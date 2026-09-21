<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('concept_hierarchy_edges', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('scheme_id')->constrained('concept_schemes')->cascadeOnDelete();
            $table->foreignId('parent_concept_id')->constrained('concepts')->restrictOnDelete();
            $table->foreignId('child_concept_id')->constrained('concepts')->restrictOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by_actor_id')->constrained('actors')->restrictOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(
                ['scheme_id', 'parent_concept_id', 'child_concept_id'],
                'concept_hierarchy_edges_identity_unique',
            );
            $table->index(['scheme_id', 'parent_concept_id', 'sort_order'], 'concept_hierarchy_parent_order_index');
        });

        Schema::create('concept_closure', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('scheme_id')->constrained('concept_schemes')->cascadeOnDelete();
            $table->foreignId('ancestor_concept_id')->constrained('concepts')->restrictOnDelete();
            $table->foreignId('descendant_concept_id')->constrained('concepts')->restrictOnDelete();
            $table->unsignedInteger('min_depth');

            $table->unique(
                ['scheme_id', 'ancestor_concept_id', 'descendant_concept_id'],
                'concept_closure_identity_unique',
            );
            $table->index(['scheme_id', 'descendant_concept_id', 'min_depth'], 'concept_closure_descendant_index');
        });

        Schema::create('concept_relation_types', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('inverse_key')->nullable();
            $table->boolean('symmetric')->default(false);
            $table->boolean('transitive')->default(false);
            $table->string('status', 32)->default('active');
            $table->timestamps();
        });

        $now = now();
        DB::table('concept_relation_types')->insert([
            ['key' => 'related_to', 'inverse_key' => 'related_to', 'symmetric' => true, 'transitive' => false, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'prerequisite_of', 'inverse_key' => 'has_prerequisite', 'symmetric' => false, 'transitive' => true, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'has_prerequisite', 'inverse_key' => 'prerequisite_of', 'symmetric' => false, 'transitive' => true, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'equivalent_to', 'inverse_key' => 'equivalent_to', 'symmetric' => true, 'transitive' => true, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'supersedes', 'inverse_key' => 'superseded_by', 'symmetric' => false, 'transitive' => true, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'superseded_by', 'inverse_key' => 'supersedes', 'symmetric' => false, 'transitive' => true, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now],
        ]);

        Schema::create('concept_relations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('from_concept_id')->constrained('concepts')->restrictOnDelete();
            $table->foreignId('relation_type_id')->constrained('concept_relation_types')->restrictOnDelete();
            $table->foreignId('to_concept_id')->constrained('concepts')->restrictOnDelete();
            $table->decimal('weight', 8, 4)->nullable();
            $table->decimal('confidence', 5, 4)->nullable();
            $table->string('source', 32)->default('manual');
            $table->foreignId('created_by_actor_id')->constrained('actors')->restrictOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(
                ['from_concept_id', 'relation_type_id', 'to_concept_id'],
                'concept_relations_identity_unique',
            );
            $table->index(['relation_type_id', 'to_concept_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('concept_relations');
        Schema::dropIfExists('concept_relation_types');
        Schema::dropIfExists('concept_closure');
        Schema::dropIfExists('concept_hierarchy_edges');
    }
};
