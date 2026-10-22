<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v1.12 — professional messaging on the existing conversation engine:
 * direct conversations (a Context of kind "direct" for exactly two people),
 * contacts, blocks, reports, per-person read state, reactions, message kinds
 * (voice, location, contact card) and duplicate-safe sending. Additive only:
 * existing conversations and messages are untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('direct_contexts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('context_id')->unique('direct_ctx_context_uq')->constrained('contexts')->restrictOnDelete();
            // The two people, lower actor id first, so a pair has exactly one row.
            $table->foreignId('actor_one_id')->constrained('actors')->restrictOnDelete();
            $table->foreignId('actor_two_id')->constrained('actors')->restrictOnDelete();
            $table->foreignId('created_by_actor_id')->constrained('actors')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['actor_one_id', 'actor_two_id'], 'direct_ctx_pair_uq');
            $table->index('actor_two_id', 'direct_ctx_two_ix');
        });

        Schema::create('actor_contacts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('actor_one_id')->constrained('actors')->restrictOnDelete();
            $table->foreignId('actor_two_id')->constrained('actors')->restrictOnDelete();
            $table->foreignId('requested_by_actor_id')->constrained('actors')->restrictOnDelete();
            $table->string('status', 16)->default('pending');
            $table->string('note', 300)->nullable();
            $table->dateTime('responded_at')->nullable();
            $table->timestamps();

            $table->unique(['actor_one_id', 'actor_two_id'], 'actor_contacts_pair_uq');
            $table->index(['actor_two_id', 'status'], 'actor_contacts_two_status_ix');
        });

        Schema::create('actor_blocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('blocker_actor_id')->constrained('actors')->restrictOnDelete();
            $table->foreignId('blocked_actor_id')->constrained('actors')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['blocker_actor_id', 'blocked_actor_id'], 'actor_blocks_pair_uq');
            $table->index('blocked_actor_id', 'actor_blocks_blocked_ix');
        });

        Schema::create('message_reports', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('reporter_actor_id')->constrained('actors')->restrictOnDelete();
            $table->foreignId('reported_actor_id')->constrained('actors')->restrictOnDelete();
            $table->foreignId('conversation_message_id')->nullable()->constrained('conversation_messages')->restrictOnDelete();
            $table->string('reason', 32);
            $table->text('details')->nullable();
            $table->string('status', 16)->default('open');
            $table->foreignId('reviewed_by_actor_id')->nullable()->constrained('actors')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at'], 'message_reports_status_ix');
        });

        Schema::create('conversation_participants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained('conversations')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('actors')->restrictOnDelete();
            $table->unsignedBigInteger('last_read_message_id')->nullable();
            $table->dateTime('last_seen_at')->nullable();
            $table->dateTime('muted_until')->nullable();
            $table->dateTime('archived_at')->nullable();
            $table->dateTime('pinned_at')->nullable();
            $table->timestamps();

            $table->unique(['conversation_id', 'actor_id'], 'conv_participants_uq');
            $table->index(['actor_id', 'archived_at'], 'conv_participants_actor_ix');
        });

        Schema::create('conversation_message_reactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_message_id')->constrained('conversation_messages')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('actors')->restrictOnDelete();
            $table->string('emoji', 16);
            $table->dateTime('created_at');

            $table->unique(['conversation_message_id', 'actor_id', 'emoji'], 'conv_reactions_uq');
        });

        Schema::table('conversation_messages', function (Blueprint $table): void {
            $table->string('kind', 16)->default('text')->after('body');
            $table->json('meta')->nullable()->after('kind');
            $table->string('client_uuid', 36)->nullable()->after('meta');

            $table->unique(['author_actor_id', 'client_uuid'], 'conv_messages_client_uq');
        });

        Schema::table('users', function (Blueprint $table): void {
            // Who may start a conversation with this person: everyone / contacts / nobody.
            $table->string('message_privacy', 16)->default('contacts');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('message_privacy'));
        // MySQL/MariaDB may let the author foreign key use the unique index below;
        // give the key an index of its own first (kept afterwards, harmless).
        if (! Schema::hasIndex('conversation_messages', 'conv_messages_author_ix')) {
            Schema::table('conversation_messages', fn (Blueprint $table) => $table->index('author_actor_id', 'conv_messages_author_ix'));
        }
        Schema::table('conversation_messages', function (Blueprint $table): void {
            $table->dropUnique('conv_messages_client_uq');
        });
        Schema::table('conversation_messages', fn (Blueprint $table) => $table->dropColumn(['kind', 'meta', 'client_uuid']));
        Schema::dropIfExists('conversation_message_reactions');
        Schema::dropIfExists('conversation_participants');
        Schema::dropIfExists('message_reports');
        Schema::dropIfExists('actor_blocks');
        Schema::dropIfExists('actor_contacts');
        Schema::dropIfExists('direct_contexts');
    }
};
