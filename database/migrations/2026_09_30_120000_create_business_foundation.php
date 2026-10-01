<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('businesses')) {
            Schema::create('businesses', function (Blueprint $table): void {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('code', 24)->unique();

                $table->foreignId('owner_actor_id')
                    ->constrained('actors')
                    ->restrictOnDelete();

                $table->string('name', 180);
                $table->string('legal_name', 220)->nullable();
                $table->string('kind', 40)->index();
                $table->string('short_intro', 300)->nullable();
                $table->text('description')->nullable();

                $table->unsignedSmallInteger('founded_year')->nullable();
                $table->string('status', 24)->default('active')->index();
                $table->string('visibility', 20)->default('private')->index();

                $table->timestamps();
            });
        }

        if (! Schema::hasTable('business_memberships')) {
            Schema::create('business_memberships', function (Blueprint $table): void {
                $table->id();

                $table->foreignId('business_id')
                    ->constrained('businesses')
                    ->cascadeOnDelete();

                $table->foreignId('actor_id')
                    ->constrained('actors')
                    ->restrictOnDelete();

                $table->string('role', 24)->default('member')->index();
                $table->string('job_title', 160)->nullable();
                $table->string('status', 24)->default('active')->index();

                $table->timestamp('joined_at')->nullable();
                $table->timestamp('ended_at')->nullable();

                $table->timestamps();

                $table->unique(['business_id', 'actor_id']);
                $table->index(['business_id', 'role', 'status']);
            });
        }

        if (! Schema::hasTable('professions')) {
            Schema::create('professions', function (Blueprint $table): void {
                $table->id();

                $table->foreignId('parent_id')
                    ->nullable()
                    ->constrained('professions')
                    ->nullOnDelete();

                $table->string('code', 100)->unique();
                $table->string('name', 160);
                $table->string('name_fa', 160)->nullable();
                $table->text('description')->nullable();

                $table->boolean('is_active')->default(true)->index();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['parent_id', 'sort_order']);
            });
        }

        if (! Schema::hasTable('actor_professions')) {
            Schema::create('actor_professions', function (Blueprint $table): void {
                $table->id();

                $table->foreignId('actor_id')
                    ->constrained('actors')
                    ->restrictOnDelete();

                $table->foreignId('profession_id')
                    ->constrained('professions')
                    ->cascadeOnDelete();

                $table->string('level', 24)->default('intermediate');
                $table->unsignedSmallInteger('years_experience')->nullable();
                $table->boolean('is_primary')->default(false)->index();
                $table->string('visibility', 20)->default('private')->index();

                $table->timestamps();

                $table->unique(
                    ['actor_id', 'profession_id'],
                    'actor_professions_actor_profession_unique'
                );
            });
        }

        if (! Schema::hasTable('business_membership_profession')) {
            Schema::create('business_membership_profession', function (Blueprint $table): void {
                $table->id();

                $table->foreignId('business_membership_id')
                    ->constrained('business_memberships')
                    ->cascadeOnDelete();

                $table->foreignId('profession_id')
                    ->constrained('professions')
                    ->cascadeOnDelete();

                $table->boolean('is_primary')->default(false);
                $table->timestamps();

                $table->unique(
                    ['business_membership_id', 'profession_id'],
                    'business_membership_prof_unique'
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('business_membership_profession');
        Schema::dropIfExists('actor_professions');
        Schema::dropIfExists('professions');
        Schema::dropIfExists('business_memberships');
        Schema::dropIfExists('businesses');
    }
};
