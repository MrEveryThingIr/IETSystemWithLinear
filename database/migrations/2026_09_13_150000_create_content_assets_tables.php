<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('asset_uuid_uq');
            $table->foreignId('group_space_id');
            $table->string('original_filename');
            $table->string('mime_type', 120);
            $table->string('extension', 32)->nullable();
            $table->unsignedBigInteger('byte_size');
            $table->string('disk', 64)->default('local');
            $table->string('storage_key', 500)->unique('asset_storage_key_uq');
            $table->char('sha256', 64);
            $table->foreignId('uploaded_by_actor_id');
            $table->string('scan_status', 32)->default('unavailable');
            $table->string('processing_status', 32)->default('ready');
            $table->string('rights_status', 32)->default('private_study_only');
            $table->text('source_attribution')->nullable();
            $table->string('alt_text', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('group_space_id', 'asset_space_fk')
                ->references('id')->on('group_spaces')->restrictOnDelete();
            $table->foreign('uploaded_by_actor_id', 'asset_uploader_fk')
                ->references('id')->on('actors')->restrictOnDelete();
            $table->index(['group_space_id', 'created_at'], 'asset_space_created_ix');
            $table->index(['uploaded_by_actor_id', 'created_at'], 'asset_uploader_created_ix');
        });

        Schema::create('space_content_revision_assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('space_content_revision_id');
            $table->foreignId('asset_id');
            $table->string('role', 32)->default('inline');
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('caption', 1000)->nullable();
            $table->timestamps();

            $table->foreign('space_content_revision_id', 'scra_revision_fk')
                ->references('id')->on('space_content_revisions')->restrictOnDelete();
            $table->foreign('asset_id', 'scra_asset_fk')
                ->references('id')->on('assets')->restrictOnDelete();
            $table->unique(['space_content_revision_id', 'asset_id'], 'scra_revision_asset_uq');
            $table->unique(['space_content_revision_id', 'position'], 'scra_revision_position_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('space_content_revision_assets');
        Schema::dropIfExists('assets');
    }
};
