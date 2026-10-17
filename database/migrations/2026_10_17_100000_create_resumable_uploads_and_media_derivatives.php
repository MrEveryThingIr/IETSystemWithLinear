<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Educational video foundation.
 *
 * media_upload_sessions  resumable, chunked uploads that never send a whole
 *                        file through one PHP request (shared-host safe).
 * asset_media_variants   derivatives of an immutable Asset: poster frame,
 *                        original metadata, and later streaming renditions.
 * asset_media_tracks     captions / subtitles / transcripts for an Asset.
 *
 * The Asset row stays the immutable provenance object; nothing here changes it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_upload_sessions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('context_id');
            $table->foreignId('space_content_id');
            $table->foreignId('actor_id');
            $table->string('original_filename', 255);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('byte_size');
            $table->unsignedInteger('chunk_size');
            $table->unsignedBigInteger('received_bytes')->default(0);
            $table->string('status', 16);
            $table->string('temp_key', 255);
            $table->string('rights_status', 32);
            $table->string('caption', 1000)->nullable();
            $table->foreignId('asset_id')->nullable();
            $table->string('error', 500)->nullable();
            $table->dateTime('expires_at');
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('context_id', 'mus_context_fk')->references('id')->on('contexts')->restrictOnDelete();
            $table->foreign('space_content_id', 'mus_content_fk')->references('id')->on('space_contents')->restrictOnDelete();
            $table->foreign('actor_id', 'mus_actor_fk')->references('id')->on('actors')->restrictOnDelete();
            $table->foreign('asset_id', 'mus_asset_fk')->references('id')->on('assets')->restrictOnDelete();
            $table->index(['actor_id', 'status'], 'mus_actor_status_idx');
            $table->index(['status', 'expires_at'], 'mus_status_expires_idx');
        });

        Schema::create('asset_media_variants', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('asset_id');
            $table->string('type', 16);
            $table->string('profile', 32)->default('default');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedBigInteger('duration_ms')->nullable();
            $table->unsignedInteger('bitrate')->nullable();
            $table->string('codec', 64)->nullable();
            $table->string('container', 32)->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->string('disk', 32)->nullable();
            $table->string('storage_key', 255)->nullable();
            $table->string('provider_key', 255)->nullable();
            $table->unsignedBigInteger('byte_size')->nullable();
            $table->string('processing_status', 16);
            $table->timestamps();

            $table->foreign('asset_id', 'amv_asset_fk')->references('id')->on('assets')->restrictOnDelete();
            $table->unique(['asset_id', 'type', 'profile'], 'amv_asset_type_profile_uq');
        });

        Schema::create('asset_media_tracks', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('asset_id');
            $table->string('kind', 16);
            $table->string('language', 16);
            $table->string('label', 120);
            $table->string('disk', 32);
            $table->string('storage_key', 255);
            $table->unsignedBigInteger('byte_size');
            $table->boolean('is_default')->default(false);
            $table->foreignId('created_by_actor_id');
            $table->timestamps();

            $table->foreign('asset_id', 'amt_asset_fk')->references('id')->on('assets')->restrictOnDelete();
            $table->foreign('created_by_actor_id', 'amt_actor_fk')->references('id')->on('actors')->restrictOnDelete();
            $table->index(['asset_id', 'kind'], 'amt_asset_kind_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_media_tracks');
        Schema::dropIfExists('asset_media_variants');
        Schema::dropIfExists('media_upload_sessions');
    }
};
