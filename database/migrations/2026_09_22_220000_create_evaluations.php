<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('evaluation_uuid_uq');
            $table->foreignId('submission_id');
            $table->foreignId('evaluator_actor_id');
            $table->string('status', 16)->default('draft');
            $table->decimal('score', 14, 4)->nullable();
            $table->json('criterion_results')->nullable();
            $table->text('feedback')->nullable();
            $table->unsignedSmallInteger('evidence_schema_version')->nullable();
            $table->char('evidence_hash', 64)->nullable();
            $table->longText('canonical_evidence')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();

            $table->foreign('submission_id', 'evaluation_submission_fk')
                ->references('id')->on('submissions')->restrictOnDelete();
            $table->foreign('evaluator_actor_id', 'evaluation_actor_fk')
                ->references('id')->on('actors')->restrictOnDelete();

            $table->unique(['submission_id', 'evaluator_actor_id'], 'evaluation_submission_actor_uq');
            $table->index(['submission_id', 'status'], 'evaluation_submission_status_ix');
            $table->index(['evaluator_actor_id', 'status'], 'evaluation_actor_status_ix');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluations');
    }
};
