<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * v1.8 — shared categories, private categories with review, and capabilities.
 * Additive only: existing professions, proficiencies and concepts are kept.
 */
return new class extends Migration
{
    public function up(): void
    {
        // What a category can describe (several per category).
        Schema::create('concept_kinds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('concept_id')->constrained('concepts')->cascadeOnDelete();
            $table->string('kind', 32);
            $table->timestamps();

            $table->unique(['concept_id', 'kind']);
            $table->index('kind');
        });

        // A private category proposed for everyone, and its review.
        Schema::create('concept_suggestions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('concept_id')->constrained('concepts')->restrictOnDelete();
            $table->foreignId('parent_concept_id')->nullable()->constrained('concepts')->nullOnDelete();
            $table->foreignId('suggested_by_actor_id')->constrained('actors')->restrictOnDelete();
            $table->text('note')->nullable();
            $table->string('status', 16)->default('pending');
            $table->foreignId('reviewed_by_actor_id')->nullable()->constrained('actors')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->foreignId('result_concept_id')->nullable()->constrained('concepts')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('concept_id');
        });

        // Old skill list → shared category (kept for compatibility).
        Schema::table('professions', function (Blueprint $table): void {
            $table->unsignedBigInteger('concept_id')->nullable()->after('code');
            $table->index('concept_id');
        });

        // What a person, business or group can do. Prices live in Offers, not here.
        Schema::create('capabilities', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('holder_actor_id')->nullable()->constrained('actors')->restrictOnDelete();
            $table->foreignId('holder_business_id')->nullable()->constrained('businesses')->restrictOnDelete();
            $table->foreignId('holder_group_id')->nullable()->constrained('groups')->restrictOnDelete();
            $table->foreignId('business_membership_id')->nullable()->constrained('business_memberships')->nullOnDelete();
            $table->foreignId('concept_id')->constrained('concepts')->restrictOnDelete();
            $table->string('title', 160)->nullable();
            $table->text('description')->nullable();
            $table->string('level', 16)->nullable();
            $table->unsignedSmallInteger('years_experience')->nullable();
            $table->unsignedTinyInteger('self_confidence_percent')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->string('visibility', 16)->default('private');
            $table->string('status', 16)->default('active');
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by_actor_id')->constrained('actors')->restrictOnDelete();
            $table->unsignedBigInteger('legacy_actor_profession_id')->nullable()->unique();
            $table->unsignedBigInteger('legacy_membership_profession_id')->nullable()->unique();
            $table->timestamps();

            $table->index(['holder_actor_id', 'status']);
            $table->index(['holder_business_id', 'status']);
            $table->index(['holder_group_id', 'status']);
            $table->index(['concept_id', 'status', 'visibility']);
        });

        // A private category sits under a shared (or another private) one.
        $now = now();
        foreach ([['narrower_than', 'broader_than'], ['broader_than', 'narrower_than']] as [$key, $inverse]) {
            if (! DB::table('concept_relation_types')->where('key', $key)->exists()) {
                DB::table('concept_relation_types')->insert([
                    'key' => $key,
                    'inverse_key' => $inverse,
                    'symmetric' => false,
                    'transitive' => true,
                    'status' => 'active',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('capabilities');
        if (Schema::hasIndex('professions', ['concept_id'])) {
            Schema::table('professions', fn (Blueprint $table) => $table->dropIndex(['concept_id']));
        }
        if (Schema::hasColumn('professions', 'concept_id')) {
            Schema::table('professions', fn (Blueprint $table) => $table->dropColumn('concept_id'));
        }
        Schema::dropIfExists('concept_suggestions');
        Schema::dropIfExists('concept_kinds');
        // Kept when private categories already use them (older code ignores them).
        DB::table('concept_relation_types')
            ->whereIn('key', ['narrower_than', 'broader_than'])
            ->whereNotIn('id', DB::table('concept_relations')->select('relation_type_id'))
            ->delete();
    }
};
