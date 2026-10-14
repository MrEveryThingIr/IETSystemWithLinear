<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Conversation messages stay immutable evidence. Edits and deletions are
        // appended here; readers show the latest revision of each message.
        Schema::create('conversation_message_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_message_id');
            $table->foreignId('actor_id');
            $table->string('kind', 16);
            $table->text('body')->nullable();
            $table->dateTime('created_at');

            $table->foreign('conversation_message_id', 'cmr_message_fk')
                ->references('id')->on('conversation_messages')->restrictOnDelete();
            $table->foreign('actor_id', 'cmr_actor_fk')
                ->references('id')->on('actors')->restrictOnDelete();
            $table->index(['conversation_message_id', 'id'], 'cmr_message_ix');
        });

        Schema::table('space_contents', function (Blueprint $table): void {
            $table->dateTime('pinned_at')->nullable()->after('archived_at');
            $table->index(['context_id', 'pinned_at'], 'sc_context_pinned_ix');
        });
    }

    public function down(): void
    {
        Schema::table('space_contents', function (Blueprint $table): void {
            $table->dropIndex('sc_context_pinned_ix');
            $table->dropColumn('pinned_at');
        });

        Schema::dropIfExists('conversation_message_revisions');
    }
};
