<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['notifiable_type', 'notifiable_id', 'read_at']);
        });

        Schema::create('notification_outbox', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('recipient_user_id')->constrained('users')->restrictOnDelete();
            $table->string('dedupe_key', 191)->unique();
            $table->string('kind', 100);
            $table->foreignId('context_id')->nullable()->constrained('contexts')->restrictOnDelete();
            $table->string('subject_type', 120)->nullable();
            $table->uuid('subject_uuid')->nullable();
            $table->json('data');
            $table->timestamp('requested_at');
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('discarded_at')->nullable();
            $table->string('discard_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['recipient_user_id', 'dispatched_at', 'discarded_at'], 'notification_outbox_delivery_index');
            $table->index(['context_id', 'requested_at']);
            $table->index(['subject_type', 'subject_uuid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_outbox');
        Schema::dropIfExists('notifications');
    }
};
