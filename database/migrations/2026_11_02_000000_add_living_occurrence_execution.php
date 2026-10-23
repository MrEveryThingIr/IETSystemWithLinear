<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_execution_policies', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('plan_id')->constrained('plans')->restrictOnDelete();
            $table->foreignId('created_by_actor_id')->constrained('actors')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('window_style', 20);
            $table->unsignedInteger('reporting_days')->default(30);
            $table->dateTime('created_at');
            $table->unique(['plan_id', 'version'], 'execution_policy_version_unique');
        });
        Schema::table('plan_schedule_rules', function (Blueprint $table): void {
            $table->foreignId('execution_policy_id')->nullable()->constrained('plan_execution_policies')->restrictOnDelete();
        });
        Schema::table('plan_occurrences', function (Blueprint $table): void {
            $table->string('execution_phase', 24)->nullable();
            $table->string('evaluated_outcome', 24)->nullable();
            $table->string('evidence_confidence', 32)->nullable();
            $table->dateTime('window_closed_at')->nullable()->index();
            $table->json('policy_snapshot')->nullable();
            $table->unsignedInteger('active_seconds')->default(0);
        });
        Schema::create('plan_occurrence_participations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plan_occurrence_id')->constrained('plan_occurrences')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('actors')->restrictOnDelete();
            $table->string('phase', 24)->default('not_started');
            $table->string('outcome', 24)->nullable();
            $table->string('confidence', 32)->default('self_reported');
            $table->dateTime('acknowledged_at')->nullable();
            $table->dateTime('attended_at')->nullable();
            $table->dateTime('actual_start_at')->nullable();
            $table->dateTime('actual_end_at')->nullable();
            $table->unsignedInteger('active_seconds')->default(0);
            $table->timestamps();
            $table->unique(['plan_occurrence_id', 'actor_id'], 'occurrence_participant_unique');
        });
        Schema::create('plan_execution_segments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('plan_occurrence_id')->constrained('plan_occurrences')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('actors')->restrictOnDelete();
            $table->dateTime('started_at');
            $table->dateTime('ended_at')->nullable();
            $table->string('closure_reason', 24)->nullable();
            $table->dateTime('created_at');
            $table->index(['plan_occurrence_id', 'actor_id'], 'execution_segment_participant_idx');
        });
        Schema::table('plan_occurrence_events', function (Blueprint $table): void {
            $table->foreignId('actor_id')->nullable()->change();
            $table->dateTime('effective_at')->nullable();
            $table->string('source', 24)->default('user');
            $table->string('idempotency_key', 160)->nullable();
            $table->unique(['plan_occurrence_id', 'idempotency_key'], 'occurrence_event_retry_unique');
        });
    }

    public function down(): void
    {
        Schema::table('plan_occurrence_events', function (Blueprint $table): void {
            $table->dropUnique('occurrence_event_retry_unique');
            $table->dropColumn(['effective_at', 'source', 'idempotency_key']);
        });
        // Preserve nullable system attribution rather than inventing a human actor.
        Schema::dropIfExists('plan_execution_segments');
        Schema::dropIfExists('plan_occurrence_participations');
        Schema::table('plan_occurrences', function (Blueprint $table): void {
            $table->dropIndex(['window_closed_at']);
            $table->dropColumn(['execution_phase', 'evaluated_outcome', 'evidence_confidence', 'window_closed_at', 'policy_snapshot', 'active_seconds']);
        });
        Schema::table('plan_schedule_rules', function (Blueprint $table): void {
            $table->dropForeign(['execution_policy_id']);
            $table->dropColumn('execution_policy_id');
        });
        Schema::dropIfExists('plan_execution_policies');
    }
};
