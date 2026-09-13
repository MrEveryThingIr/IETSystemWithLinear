<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('space_content_definitions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('group_space_id')->constrained('group_spaces')->restrictOnDelete();
            $table->foreignId('created_by_actor_id')->constrained('actors')->restrictOnDelete();
            $table->string('name', 120);
            $table->string('slug', 120);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('current_version')->default(1);
            $table->timestamps();

            $table->unique(['group_space_id', 'slug']);
            $table->index(['group_space_id', 'status']);
        });

        Schema::create('space_content_definition_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('space_content_definition_id')->constrained('space_content_definitions')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->json('schema');
            $table->json('display')->nullable();
            $table->foreignId('created_by_actor_id')->constrained('actors')->restrictOnDelete();
            $table->char('content_hash', 64);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['space_content_definition_id', 'version'], 'space_content_definition_versions_definition_version_unique');
            $table->index(['space_content_definition_id', 'published_at'], 'space_content_definition_versions_published_index');
        });

        Schema::create('space_contents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('group_space_id')->constrained('group_spaces')->restrictOnDelete();
            $table->foreignId('space_content_definition_id')->constrained('space_content_definitions')->restrictOnDelete();
            $table->foreignId('author_actor_id')->constrained('actors')->restrictOnDelete();
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('current_revision')->default(1);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['group_space_id', 'status']);
            $table->index(['author_actor_id', 'status']);
            $table->index(['space_content_definition_id', 'status'], 'space_contents_definition_status_index');
        });

        Schema::create('space_content_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('space_content_id')->constrained('space_contents')->restrictOnDelete();
            $table->foreignId('definition_version_id')->constrained('space_content_definition_versions')->restrictOnDelete();
            $table->unsignedInteger('revision');
            $table->string('title');
            $table->json('payload');
            $table->foreignId('created_by_actor_id')->constrained('actors')->restrictOnDelete();
            $table->char('content_hash', 64);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['space_content_id', 'revision']);
            $table->index(['definition_version_id', 'space_content_id'], 'space_content_revisions_definition_content_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('space_content_revisions');
        Schema::dropIfExists('space_contents');
        Schema::dropIfExists('space_content_definition_versions');
        Schema::dropIfExists('space_content_definitions');
    }
};
