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
            $table->foreignId('group_space_id');
            $table->foreignId('created_by_actor_id');
            $table->string('name', 120);
            $table->string('slug', 120);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('current_version')->default(1);
            $table->timestamps();

            $table->foreign('group_space_id', 'scd_space_fk')->references('id')->on('group_spaces')->restrictOnDelete();
            $table->foreign('created_by_actor_id', 'scd_creator_fk')->references('id')->on('actors')->restrictOnDelete();
            $table->unique(['group_space_id', 'slug'], 'scd_space_slug_uq');
            $table->index(['group_space_id', 'status'], 'scd_space_status_ix');
        });

        Schema::create('space_content_definition_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('space_content_definition_id');
            $table->unsignedInteger('version');
            $table->json('schema');
            $table->json('display')->nullable();
            $table->foreignId('created_by_actor_id');
            $table->char('content_hash', 64);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->foreign('space_content_definition_id', 'scdv_definition_fk')->references('id')->on('space_content_definitions')->restrictOnDelete();
            $table->foreign('created_by_actor_id', 'scdv_creator_fk')->references('id')->on('actors')->restrictOnDelete();
            $table->unique(['space_content_definition_id', 'version'], 'scdv_definition_version_uq');
            $table->index(['space_content_definition_id', 'published_at'], 'scdv_definition_published_ix');
        });

        Schema::create('space_contents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('group_space_id');
            $table->foreignId('space_content_definition_id');
            $table->foreignId('author_actor_id');
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('current_revision')->default(1);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->foreign('group_space_id', 'sc_space_fk')->references('id')->on('group_spaces')->restrictOnDelete();
            $table->foreign('space_content_definition_id', 'sc_definition_fk')->references('id')->on('space_content_definitions')->restrictOnDelete();
            $table->foreign('author_actor_id', 'sc_author_fk')->references('id')->on('actors')->restrictOnDelete();
            $table->index(['group_space_id', 'status'], 'sc_space_status_ix');
            $table->index(['author_actor_id', 'status'], 'sc_author_status_ix');
            $table->index(['space_content_definition_id', 'status'], 'sc_definition_status_ix');
        });

        Schema::create('space_content_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('space_content_id');
            $table->foreignId('definition_version_id');
            $table->unsignedInteger('revision');
            $table->string('title');
            $table->json('payload');
            $table->foreignId('created_by_actor_id');
            $table->char('content_hash', 64);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('space_content_id', 'scr_content_fk')->references('id')->on('space_contents')->restrictOnDelete();
            $table->foreign('definition_version_id', 'scr_definition_version_fk')->references('id')->on('space_content_definition_versions')->restrictOnDelete();
            $table->foreign('created_by_actor_id', 'scr_creator_fk')->references('id')->on('actors')->restrictOnDelete();
            $table->unique(['space_content_id', 'revision'], 'scr_content_revision_uq');
            $table->index(['definition_version_id', 'space_content_id'], 'scr_definition_content_ix');
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
