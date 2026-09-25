<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('domain_blueprints', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('slug', 120)->unique();
            $table->string('name', 180);
            $table->string('description', 1000)->nullable();
            $table->string('category', 80);
            $table->string('status', 24)->default('active');
            $table->unsignedInteger('current_version')->default(0);
            $table->timestamps();

            $table->index(['category', 'status']);
        });

        Schema::create('domain_blueprint_versions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('domain_blueprint_id')
                ->constrained('domain_blueprints')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('journey_kind', 40);
            $table->json('terminology');
            $table->json('capabilities');
            $table->json('content_blueprint_slugs');
            $table->json('guided_entry');
            $table->foreignId('created_by_actor_id')->nullable()
                ->constrained('actors')->restrictOnDelete();
            $table->char('content_hash', 64);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['domain_blueprint_id', 'version']);
            $table->index(['journey_kind', 'published_at']);
        });

        Schema::table('relationships', function (Blueprint $table): void {
            $table->foreignId('domain_blueprint_version_id')->nullable()
                ->after('originating_intent_id')
                ->constrained('domain_blueprint_versions')
                ->restrictOnDelete();
        });

        Schema::table('plans', function (Blueprint $table): void {
            $table->foreignId('domain_blueprint_version_id')->nullable()
                ->after('context_id')
                ->constrained('domain_blueprint_versions')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('domain_blueprint_version_id');
        });

        Schema::table('relationships', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('domain_blueprint_version_id');
        });

        Schema::dropIfExists('domain_blueprint_versions');
        Schema::dropIfExists('domain_blueprints');
    }
};
