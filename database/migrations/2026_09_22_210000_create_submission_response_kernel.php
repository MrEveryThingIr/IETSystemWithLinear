<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submissions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('submission_uuid_uq');
            $table->foreignId('context_id');
            $table->foreignId('interaction_definition_version_id');
            $table->foreignId('space_content_revision_id')->nullable();
            $table->foreignId('submitted_by_actor_id');
            $table->unsignedInteger('attempt_number');
            $table->string('status', 16)->default('draft');
            $table->unsignedSmallInteger('evidence_schema_version')->nullable();
            $table->char('evidence_hash', 64)->nullable();
            $table->longText('canonical_evidence')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamps();

            $table->foreign('context_id', 'submission_context_fk')
                ->references('id')->on('contexts')->restrictOnDelete();
            $table->foreign('interaction_definition_version_id', 'submission_definition_version_fk')
                ->references('id')->on('interaction_definition_versions')->restrictOnDelete();
            $table->foreign('space_content_revision_id', 'submission_content_revision_fk')
                ->references('id')->on('space_content_revisions')->restrictOnDelete();
            $table->foreign('submitted_by_actor_id', 'submission_actor_fk')
                ->references('id')->on('actors')->restrictOnDelete();

            $table->unique(
                ['interaction_definition_version_id', 'submitted_by_actor_id', 'attempt_number'],
                'submission_version_actor_attempt_uq',
            );
            $table->index(['context_id', 'status', 'created_at'], 'submission_context_status_created_ix');
            $table->index(['submitted_by_actor_id', 'status'], 'submission_actor_status_ix');
        });

        Schema::create('submission_responses', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('submission_response_uuid_uq');
            $table->foreignId('submission_id');
            $table->string('item_key', 64);
            $table->string('response_type', 32);
            $table->json('value')->nullable();
            $table->foreignId('asset_id')->nullable();
            $table->foreignId('content_evidence_reference_id')->nullable();
            $table->timestamps();

            $table->foreign('submission_id', 'submission_response_submission_fk')
                ->references('id')->on('submissions')->restrictOnDelete();
            $table->foreign('asset_id', 'submission_response_asset_fk')
                ->references('id')->on('assets')->restrictOnDelete();
            $table->foreign('content_evidence_reference_id', 'submission_response_evidence_fk')
                ->references('id')->on('content_evidence_references')->restrictOnDelete();

            $table->unique(['submission_id', 'item_key'], 'submission_response_item_uq');
            $table->index('asset_id', 'submission_response_asset_ix');
            $table->index('content_evidence_reference_id', 'submission_response_evidence_ix');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_responses');
        Schema::dropIfExists('submissions');
    }
};
