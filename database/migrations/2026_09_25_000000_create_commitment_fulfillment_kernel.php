<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commitments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('contract_version_id')
                ->constrained('contract_versions')->restrictOnDelete();
            $table->foreignId('created_by_actor_id')
                ->constrained('actors')->restrictOnDelete();
            $table->foreignId('obligor_actor_id')
                ->constrained('actors')->restrictOnDelete();
            $table->foreignId('beneficiary_actor_id')
                ->constrained('actors')->restrictOnDelete();
            $table->string('kind', 32);
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->decimal('quantity', 18, 4);
            $table->string('unit', 40);
            $table->timestamp('due_start_at')->nullable();
            $table->timestamp('due_end_at')->nullable();
            $table->string('status', 24)->default('active');
            $table->timestamps();

            $table->index(['contract_version_id', 'status'], 'commitments_version_status_index');
            $table->index(['obligor_actor_id', 'status'], 'commitments_obligor_status_index');
            $table->index(['beneficiary_actor_id', 'status'], 'commitments_beneficiary_status_index');
        });

        Schema::create('commitment_plan_bindings', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('commitment_id')->unique()
                ->constrained('commitments')->restrictOnDelete();
            $table->foreignId('plan_id')->unique()
                ->constrained('plans')->restrictOnDelete();
            $table->foreignId('bound_by_actor_id')
                ->constrained('actors')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('commitment_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('commitment_id')
                ->constrained('commitments')->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()
                ->constrained('actors')->restrictOnDelete();
            $table->string('event_type', 40);
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['commitment_id', 'id']);
        });

        Schema::create('fulfillments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('commitment_id')
                ->constrained('commitments')->restrictOnDelete();
            $table->foreignId('plan_occurrence_id')->nullable()
                ->constrained('plan_occurrences')->restrictOnDelete();
            $table->foreignId('corrects_fulfillment_id')->nullable()->unique()
                ->constrained('fulfillments')->restrictOnDelete();
            $table->foreignId('submitted_by_actor_id')
                ->constrained('actors')->restrictOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->string('unit', 40);
            $table->timestamp('actual_start_at')->nullable();
            $table->timestamp('actual_end_at')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 32)->default('submitted');
            $table->timestamp('submitted_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['commitment_id', 'status'], 'fulfillments_commitment_status_index');
            $table->index(['plan_occurrence_id', 'status'], 'fulfillments_occurrence_status_index');
        });

        Schema::create('fulfillment_assets', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('fulfillment_id')
                ->constrained('fulfillments')->restrictOnDelete();
            $table->foreignId('asset_id')
                ->constrained('assets')->restrictOnDelete();
            $table->foreignId('added_by_actor_id')
                ->constrained('actors')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['fulfillment_id', 'asset_id'], 'fulfillment_assets_fulfillment_asset_unique');
        });

        Schema::create('fulfillment_evidence_references', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('fulfillment_id')
                ->constrained('fulfillments')->restrictOnDelete();
            $table->foreignId('content_evidence_reference_id');
            $table->foreign(
                'content_evidence_reference_id',
                'fulfillment_evidence_reference_fk',
            )->references('id')->on('content_evidence_references')->restrictOnDelete();
            $table->foreignId('added_by_actor_id')
                ->constrained('actors')->restrictOnDelete();
            $table->timestamps();

            $table->unique(
                ['fulfillment_id', 'content_evidence_reference_id'],
                'fulfillment_evidence_fulfillment_reference_unique',
            );
        });

        Schema::create('fulfillment_reviews', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('fulfillment_id')->unique()
                ->constrained('fulfillments')->restrictOnDelete();
            $table->foreignId('reviewer_actor_id')
                ->constrained('actors')->restrictOnDelete();
            $table->string('decision', 40);
            $table->text('note')->nullable();
            $table->timestamp('reviewed_at');
            $table->timestamps();
        });

        Schema::create('fulfillment_disputes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('fulfillment_id')->unique()
                ->constrained('fulfillments')->restrictOnDelete();
            $table->foreignId('opened_by_actor_id')
                ->constrained('actors')->restrictOnDelete();
            $table->string('original_status', 32);
            $table->text('reason');
            $table->string('status', 24)->default('open');
            $table->foreignId('resolved_by_actor_id')->nullable()
                ->constrained('actors')->restrictOnDelete();
            $table->string('resolution_status', 32)->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fulfillment_disputes');
        Schema::dropIfExists('fulfillment_reviews');
        Schema::dropIfExists('fulfillment_evidence_references');
        Schema::dropIfExists('fulfillment_assets');
        Schema::dropIfExists('fulfillments');
        Schema::dropIfExists('commitment_events');
        Schema::dropIfExists('commitment_plan_bindings');
        Schema::dropIfExists('commitments');
    }
};
