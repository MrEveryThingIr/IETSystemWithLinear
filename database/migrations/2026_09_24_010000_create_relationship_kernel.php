<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('relationships', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('title', 180)->nullable();
            $table->foreignId('purpose_concept_id')->constrained('concepts')->restrictOnDelete();
            $table->foreignId('originating_intent_id')->nullable()->constrained('actor_profile_intents')->restrictOnDelete();
            $table->foreignId('created_by_actor_id')->constrained('actors')->restrictOnDelete();
            $table->string('status', 24)->default('proposed');
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'updated_at'], 'relationships_status_updated_index');
            $table->index(['purpose_concept_id', 'status'], 'relationships_purpose_status_index');
            $table->index('originating_intent_id', 'relationships_origin_intent_index');
        });

        Schema::create('relationship_participants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('relationship_id')->constrained('relationships')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('actors')->restrictOnDelete();
            $table->string('role', 80);
            $table->string('status', 24)->default('invited');
            $table->boolean('can_manage')->default(false);
            $table->foreignId('invited_by_actor_id')->constrained('actors')->restrictOnDelete();
            $table->timestamp('invited_at');
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->timestamps();

            $table->unique(['relationship_id', 'actor_id'], 'relationship_participants_unique');
            $table->index(['actor_id', 'status'], 'relationship_participants_actor_status_index');
            $table->index(['relationship_id', 'status'], 'relationship_participants_relationship_status_index');
        });

        Schema::create('relationship_contexts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('context_id')->unique()->constrained('contexts')->restrictOnDelete();
            $table->foreignId('relationship_id')->unique()->constrained('relationships')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('relationship_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('relationship_id')->constrained('relationships')->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('actors')->restrictOnDelete();
            $table->string('event_type', 40);
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['relationship_id', 'event_type'], 'relationship_events_relationship_type_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('relationship_events');
        Schema::dropIfExists('relationship_contexts');
        Schema::dropIfExists('relationship_participants');
        Schema::dropIfExists('relationships');
    }
};
