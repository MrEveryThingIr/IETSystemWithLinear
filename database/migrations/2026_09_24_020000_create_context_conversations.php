<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $this->recoverEmptyPartialAttempt();

        Schema::create('conversations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('context_id')->constrained('contexts')->restrictOnDelete();
            $table->string('key', 80)->default('main');
            $table->string('status', 24)->default('active');
            $table->foreignId('created_by_actor_id')->nullable()->constrained('actors')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['context_id', 'key'], 'conversations_context_key_unique');
            $table->index(['context_id', 'status'], 'conversations_context_status_index');
        });

        Schema::create('conversation_messages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('conversation_id')->constrained('conversations')->restrictOnDelete();
            $table->foreignId('author_actor_id')->constrained('actors')->restrictOnDelete();
            $table->foreignId('reply_to_message_id')->nullable()
                ->constrained('conversation_messages')->nullOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['conversation_id', 'id'], 'conversation_messages_conversation_id_index');
        });

        Schema::create('conversation_message_assets', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('conversation_message_id')->constrained('conversation_messages')->restrictOnDelete();
            $table->foreignId('asset_id')->constrained('assets')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['conversation_message_id', 'asset_id'], 'conversation_message_asset_unique');
        });

        Schema::create('conversation_message_evidence_references', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('conversation_message_id');
            $table->foreignId('content_evidence_reference_id');
            $table->foreign(
                'conversation_message_id',
                'conv_msg_evidence_message_fk',
            )->references('id')->on('conversation_messages')->restrictOnDelete();
            $table->foreign(
                'content_evidence_reference_id',
                'conv_msg_evidence_reference_fk',
            )->references('id')->on('content_evidence_references')->restrictOnDelete();
            $table->timestamps();

            $table->unique(
                ['conversation_message_id', 'content_evidence_reference_id'],
                'conversation_message_evidence_unique',
            );
        });

        $now = now();

        DB::table('group_space_contexts as binding')
            ->join('group_spaces as space', 'space.id', '=', 'binding.group_space_id')
            ->select(['binding.context_id', 'space.created_by_actor_id', 'space.created_at', 'space.updated_at'])
            ->orderBy('binding.id')
            ->chunk(200, function ($bindings) use ($now): void {
                foreach ($bindings as $binding) {
                    DB::table('conversations')->insertOrIgnore([
                        'uuid' => (string) Str::uuid(),
                        'context_id' => $binding->context_id,
                        'key' => 'main',
                        'status' => 'active',
                        'created_by_actor_id' => $binding->created_by_actor_id,
                        'created_at' => $binding->created_at ?? $now,
                        'updated_at' => $binding->updated_at ?? $now,
                    ]);
                }
            });

        DB::table('group_space_messages as message')
            ->join('group_space_contexts as binding', 'binding.group_space_id', '=', 'message.group_space_id')
            ->join('conversations as conversation', function ($join): void {
                $join->on('conversation.context_id', '=', 'binding.context_id')
                    ->where('conversation.key', '=', 'main');
            })
            ->select([
                'message.id as legacy_id',
                'message.author_actor_id',
                'message.reply_to_message_id',
                'message.body',
                'message.created_at',
                'message.updated_at',
                'conversation.id as conversation_id',
            ])
            ->orderBy('message.id')
            ->chunk(200, function ($messages): void {
                foreach ($messages as $message) {
                    DB::table('conversation_messages')->insert([
                        'id' => $message->legacy_id,
                        'uuid' => (string) Str::uuid(),
                        'conversation_id' => $message->conversation_id,
                        'author_actor_id' => $message->author_actor_id,
                        'reply_to_message_id' => $message->reply_to_message_id,
                        'body' => $message->body,
                        'created_at' => $message->created_at,
                        'updated_at' => $message->updated_at,
                    ]);
                }
            });

        Schema::dropIfExists('group_space_messages');
    }

    private function recoverEmptyPartialAttempt(): void
    {
        $tables = [
            'conversation_message_evidence_references',
            'conversation_message_assets',
            'conversation_messages',
            'conversations',
        ];

        $existing = array_values(array_filter(
            $tables,
            static fn (string $table): bool => Schema::hasTable($table),
        ));

        if ($existing === []) {
            return;
        }

        foreach ($existing as $table) {
            if (DB::table($table)->exists()) {
                throw new RuntimeException(
                    "Cannot automatically recover the incomplete context-conversation migration because {$table} contains data.",
                );
            }
        }

        foreach ($tables as $table) {
            Schema::dropIfExists($table);
        }
    }

    public function down(): void
    {
        Schema::create('group_space_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('group_space_id')->constrained('group_spaces')->cascadeOnDelete();
            $table->foreignId('author_actor_id')->constrained('actors')->restrictOnDelete();
            $table->foreignId('reply_to_message_id')->nullable()
                ->constrained('group_space_messages')->nullOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['group_space_id', 'id']);
        });

        DB::table('conversation_messages as message')
            ->join('conversations as conversation', 'conversation.id', '=', 'message.conversation_id')
            ->join('group_space_contexts as binding', 'binding.context_id', '=', 'conversation.context_id')
            ->select([
                'message.id',
                'binding.group_space_id',
                'message.author_actor_id',
                'message.reply_to_message_id',
                'message.body',
                'message.created_at',
                'message.updated_at',
            ])
            ->orderBy('message.id')
            ->chunk(200, function ($messages): void {
                foreach ($messages as $message) {
                    DB::table('group_space_messages')->insert((array) $message);
                }
            });

        Schema::dropIfExists('conversation_message_evidence_references');
        Schema::dropIfExists('conversation_message_assets');
        Schema::dropIfExists('conversation_messages');
        Schema::dropIfExists('conversations');
    }
};
