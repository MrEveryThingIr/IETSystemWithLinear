<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('title', 180);
            $table->foreignId('relationship_id')->nullable()
                ->constrained('relationships')->restrictOnDelete();
            $table->foreignId('source_proposal_version_id')->nullable()->unique()
                ->constrained('proposal_versions')->restrictOnDelete();
            $table->foreignId('created_by_actor_id')
                ->constrained('actors')->restrictOnDelete();
            $table->string('status', 24)->default('pending');
            $table->timestamps();

            $table->index(['status', 'id']);
        });

        Schema::create('contract_contexts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('context_id')->unique()
                ->constrained('contexts')->restrictOnDelete();
            $table->foreignId('contract_id')->unique()
                ->constrained('contracts')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('contract_versions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('contract_id')
                ->constrained('contracts')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->foreignId('terms_content_revision_id')->unique()
                ->constrained('space_content_revisions')->restrictOnDelete();
            $table->foreignId('supersedes_version_id')->nullable()
                ->constrained('contract_versions')->restrictOnDelete();
            $table->foreignId('proposed_by_actor_id')
                ->constrained('actors')->restrictOnDelete();
            $table->string('status', 24)->default('proposed');
            $table->timestamp('effective_from');
            $table->string('effective_timezone', 64);
            $table->timestamp('effective_until')->nullable();
            $table->string('note', 1000)->nullable();
            $table->timestamp('proposed_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('superseded_at')->nullable();
            $table->timestamps();

            $table->unique(['contract_id', 'version']);
            $table->index(['contract_id', 'status', 'effective_from'], 'contract_versions_contract_status_effective_index');
        });

        Schema::create('contract_version_parties', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('contract_version_id')
                ->constrained('contract_versions')->restrictOnDelete();
            $table->foreignId('actor_id')
                ->constrained('actors')->restrictOnDelete();
            $table->string('role', 80);
            $table->boolean('required')->default(true);
            $table->foreignId('source_proposal_party_id')->nullable()
                ->constrained('proposal_parties')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['contract_version_id', 'actor_id'], 'contract_version_parties_version_actor_unique');
        });

        Schema::create('contract_acceptances', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('contract_version_party_id')->unique()
                ->constrained('contract_version_parties')->restrictOnDelete();
            $table->foreignId('accepted_by_user_id')
                ->constrained('users')->restrictOnDelete();
            $table->timestamp('accepted_at');
            $table->timestamps();
        });

        Schema::create('contract_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('contract_id')
                ->constrained('contracts')->restrictOnDelete();
            $table->foreignId('contract_version_id')->nullable()
                ->constrained('contract_versions')->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()
                ->constrained('actors')->restrictOnDelete();
            $table->string('event_type', 40);
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['contract_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_events');
        Schema::dropIfExists('contract_acceptances');
        Schema::dropIfExists('contract_version_parties');
        Schema::dropIfExists('contract_versions');
        Schema::dropIfExists('contract_contexts');
        Schema::dropIfExists('contracts');
    }
};
