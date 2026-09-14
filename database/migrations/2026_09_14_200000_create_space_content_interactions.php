<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('space_content_annotations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedBigInteger('space_content_id');
            $table->unsignedBigInteger('space_content_revision_id');
            $table->unsignedBigInteger('parent_annotation_id')->nullable();
            $table->unsignedBigInteger('author_actor_id');
            $table->string('kind', 32)->default('comment');
            $table->string('visibility', 16)->default('space');
            $table->string('status', 16)->default('active');
            $table->text('body');
            $table->timestamps();

            $table->unique('uuid', 'sca_uuid_uq');
            $table->index(['space_content_revision_id', 'status', 'created_at'], 'sca_revision_status_ix');
            $table->index(['parent_annotation_id', 'status'], 'sca_parent_status_ix');
            $table->foreign('space_content_id', 'sca_content_fk')->references('id')->on('space_contents')->restrictOnDelete();
            $table->foreign('space_content_revision_id', 'sca_revision_fk')->references('id')->on('space_content_revisions')->restrictOnDelete();
            $table->foreign('parent_annotation_id', 'sca_parent_fk')->references('id')->on('space_content_annotations')->restrictOnDelete();
            $table->foreign('author_actor_id', 'sca_author_fk')->references('id')->on('actors')->restrictOnDelete();
        });

        Schema::create('space_content_reactions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedBigInteger('space_content_id');
            $table->unsignedBigInteger('space_content_revision_id');
            $table->unsignedBigInteger('actor_id');
            $table->string('type', 32);
            $table->timestamps();

            $table->unique('uuid', 'screact_uuid_uq');
            $table->unique(['space_content_revision_id', 'actor_id', 'type'], 'screact_revision_actor_type_uq');
            $table->index(['space_content_revision_id', 'type'], 'screact_revision_type_ix');
            $table->foreign('space_content_id', 'screact_content_fk')->references('id')->on('space_contents')->restrictOnDelete();
            $table->foreign('space_content_revision_id', 'screact_revision_fk')->references('id')->on('space_content_revisions')->restrictOnDelete();
            $table->foreign('actor_id', 'screact_actor_fk')->references('id')->on('actors')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('space_content_reactions');
        Schema::dropIfExists('space_content_annotations');
    }
};
