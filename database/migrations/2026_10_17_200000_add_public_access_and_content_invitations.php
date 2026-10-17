<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guest access to published Content, always chosen by its author.
 *
 * space_contents.public_access  "private" (default) or "public". Public
 *                               Content is readable by anyone, signed in or
 *                               not, and is listed on the public index. Only
 *                               the published edition is ever shown.
 * content_invitations           a link for one person to read one piece of
 *                               Content (and the lessons a course contains)
 *                               without an account. Revocable, optionally
 *                               expiring, and counted when opened.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('space_contents', function (Blueprint $table): void {
            $table->string('public_access', 16)->default('private');
            $table->dateTime('public_since')->nullable();
            $table->index(['public_access', 'status', 'published_at'], 'sc_public_listing_idx');
        });

        Schema::create('content_invitations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('ci_uuid_uq');
            $table->foreignId('space_content_id');
            $table->foreignId('created_by_actor_id');
            $table->string('token_hash', 64)->unique('ci_token_hash_uq');
            $table->text('token_encrypted');
            $table->string('invitee_name', 120);
            $table->string('message', 1000)->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('revoked_at')->nullable();
            $table->foreignId('revoked_by_actor_id')->nullable();
            $table->unsignedInteger('view_count')->default(0);
            $table->dateTime('first_viewed_at')->nullable();
            $table->dateTime('last_viewed_at')->nullable();
            $table->timestamps();

            $table->foreign('space_content_id', 'ci_content_fk')->references('id')->on('space_contents')->restrictOnDelete();
            $table->foreign('created_by_actor_id', 'ci_creator_fk')->references('id')->on('actors')->restrictOnDelete();
            $table->foreign('revoked_by_actor_id', 'ci_revoker_fk')->references('id')->on('actors')->restrictOnDelete();
            $table->index(['space_content_id', 'revoked_at'], 'ci_content_revoked_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_invitations');

        Schema::table('space_contents', function (Blueprint $table): void {
            $table->dropIndex('sc_public_listing_idx');
            $table->dropColumn(['public_access', 'public_since']);
        });
    }
};
