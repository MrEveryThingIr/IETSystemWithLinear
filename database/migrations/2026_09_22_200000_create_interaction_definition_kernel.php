<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interaction_definitions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('interaction_definition_uuid_uq');
            $table->foreignId('context_id');
            $table->foreignId('space_content_id')->nullable();
            $table->string('name', 120);
            $table->string('status', 16)->default('draft');
            $table->unsignedInteger('current_version')->default(1);
            $table->unsignedBigInteger('active_version_id')->nullable();
            $table->unsignedBigInteger('draft_version_id')->nullable();
            $table->foreignId('created_by_actor_id')->nullable();
            $table->timestamps();

            $table->foreign('context_id', 'interaction_definition_context_fk')
                ->references('id')->on('contexts')->restrictOnDelete();
            $table->foreign('space_content_id', 'interaction_definition_content_fk')
                ->references('id')->on('space_contents')->restrictOnDelete();
            $table->foreign('created_by_actor_id', 'interaction_definition_creator_fk')
                ->references('id')->on('actors')->restrictOnDelete();
            $table->index(['context_id', 'status'], 'interaction_definition_context_status_ix');
            $table->index(['space_content_id', 'status'], 'interaction_definition_content_status_ix');
        });

        Schema::create('interaction_definition_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('interaction_definition_id');
            $table->unsignedInteger('version');
            $table->string('purpose_key', 64)->default('general');
            $table->string('title', 160);
            $table->text('instructions')->nullable();
            $table->json('items');
            $table->json('settings');
            $table->json('evaluation_config');
            $table->foreignId('space_content_revision_id')->nullable();
            $table->foreignId('created_by_actor_id')->nullable();
            $table->char('content_hash', 64);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->foreign('interaction_definition_id', 'interaction_version_definition_fk')
                ->references('id')->on('interaction_definitions')->restrictOnDelete();
            $table->foreign('space_content_revision_id', 'interaction_version_content_revision_fk')
                ->references('id')->on('space_content_revisions')->restrictOnDelete();
            $table->foreign('created_by_actor_id', 'interaction_version_creator_fk')
                ->references('id')->on('actors')->restrictOnDelete();
            $table->unique(['interaction_definition_id', 'version'], 'interaction_version_number_uq');
            $table->index(['interaction_definition_id', 'published_at'], 'interaction_version_published_ix');
            $table->index('space_content_revision_id', 'interaction_version_content_revision_ix');
        });

        Schema::table('interaction_definitions', function (Blueprint $table): void {
            $table->foreign('active_version_id', 'interaction_definition_active_version_fk')
                ->references('id')->on('interaction_definition_versions')->restrictOnDelete();
            $table->foreign('draft_version_id', 'interaction_definition_draft_version_fk')
                ->references('id')->on('interaction_definition_versions')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('interaction_definitions', function (Blueprint $table): void {
            $table->dropForeign(
                DB::connection()->getDriverName() === 'sqlite'
                    ? ['active_version_id']
                    : 'interaction_definition_active_version_fk',
            );
            $table->dropForeign(
                DB::connection()->getDriverName() === 'sqlite'
                    ? ['draft_version_id']
                    : 'interaction_definition_draft_version_fk',
            );
        });

        Schema::dropIfExists('interaction_definition_versions');
        Schema::dropIfExists('interaction_definitions');
    }
};
