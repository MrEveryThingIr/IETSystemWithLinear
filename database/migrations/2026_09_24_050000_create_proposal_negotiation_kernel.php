<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposals', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('title', 180);
            $table->foreignId('relationship_id')->nullable()->constrained('relationships')->restrictOnDelete();
            $table->foreignId('created_by_actor_id')->constrained('actors')->restrictOnDelete();
            $table->string('status', 32)->default('negotiating');
            $table->timestamps();

            $table->index(['status', 'id']);
        });

        Schema::create('proposal_parties', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('proposal_id')->constrained('proposals')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('actors')->restrictOnDelete();
            $table->string('role', 80);
            $table->boolean('required')->default(true);
            $table->foreignId('added_by_actor_id')->constrained('actors')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['proposal_id', 'actor_id']);
        });

        Schema::create('proposal_contexts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('context_id')->unique()->constrained('contexts')->restrictOnDelete();
            $table->foreignId('proposal_id')->unique()->constrained('proposals')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('proposal_versions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('proposal_id')->constrained('proposals')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->foreignId('terms_content_revision_id')->unique()
                ->constrained('space_content_revisions')->restrictOnDelete();
            $table->foreignId('proposed_by_actor_id')->constrained('actors')->restrictOnDelete();
            $table->string('note', 1000)->nullable();
            $table->timestamp('proposed_at');
            $table->timestamps();

            $table->unique(['proposal_id', 'version']);
        });

        Schema::create('proposal_decisions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('proposal_version_id')->constrained('proposal_versions')->restrictOnDelete();
            $table->foreignId('proposal_party_id')->constrained('proposal_parties')->restrictOnDelete();
            $table->string('decision', 32);
            $table->string('note', 2000)->nullable();
            $table->foreignId('decided_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('decided_at');
            $table->timestamps();

            $table->unique(['proposal_version_id', 'proposal_party_id']);
        });

        Schema::create('proposal_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('proposal_id')->constrained('proposals')->restrictOnDelete();
            $table->foreignId('proposal_version_id')->nullable()
                ->constrained('proposal_versions')->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('actors')->restrictOnDelete();
            $table->string('event_type', 40);
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['proposal_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposal_events');
        Schema::dropIfExists('proposal_decisions');
        Schema::dropIfExists('proposal_versions');
        Schema::dropIfExists('proposal_contexts');
        Schema::dropIfExists('proposal_parties');
        Schema::dropIfExists('proposals');
    }
};
