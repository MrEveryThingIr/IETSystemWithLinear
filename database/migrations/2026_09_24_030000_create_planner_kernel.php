<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('context_id')->constrained('contexts')->restrictOnDelete();
            $table->foreignId('created_by_actor_id')->constrained('actors')->restrictOnDelete();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->string('timezone', 64);
            $table->string('status', 24)->default('active');
            $table->string('origin_type', 80)->nullable();
            $table->uuid('origin_uuid')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['context_id', 'status'], 'plans_context_status_index');
            $table->index(['origin_type', 'origin_uuid'], 'plans_origin_index');
        });

        Schema::create('plan_participants', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('plan_id')->constrained('plans')->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('actors')->restrictOnDelete();
            $table->foreignId('assigned_by_actor_id')->constrained('actors')->restrictOnDelete();
            $table->string('role', 80)->default('participant');
            $table->string('status', 24)->default('active');
            $table->timestamps();

            $table->unique(['plan_id', 'actor_id'], 'plan_participants_plan_actor_unique');
        });

        Schema::create('plan_schedule_rules', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('plan_id')->constrained('plans')->restrictOnDelete();
            $table->foreignId('created_by_actor_id')->constrained('actors')->restrictOnDelete();
            $table->string('frequency', 24);
            $table->unsignedSmallInteger('interval')->default(1);
            $table->date('starts_on');
            $table->string('start_time', 8);
            $table->unsignedInteger('duration_minutes')->default(60);
            $table->json('weekdays')->nullable();
            $table->json('selected_dates')->nullable();
            $table->date('ends_on')->nullable();
            $table->unsignedInteger('occurrence_limit')->nullable();
            $table->unsignedInteger('window_before_minutes')->default(0);
            $table->unsignedInteger('window_after_minutes')->default(0);
            $table->string('timezone', 64);
            $table->string('status', 24)->default('active');
            $table->timestamps();

            $table->index(['plan_id', 'status'], 'plan_schedule_rules_plan_status_index');
        });

        Schema::create('plan_reminders', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('plan_id')->constrained('plans')->restrictOnDelete();
            $table->foreignId('schedule_rule_id')->nullable()->constrained('plan_schedule_rules')->restrictOnDelete();
            $table->foreignId('created_by_actor_id')->constrained('actors')->restrictOnDelete();
            $table->unsignedInteger('minutes_before');
            $table->string('channel', 24)->default('app');
            $table->string('status', 24)->default('active');
            $table->timestamps();

            $table->unique(
                ['plan_id', 'schedule_rule_id', 'minutes_before', 'channel'],
                'plan_reminders_rule_offset_unique',
            );
        });

        Schema::create('plan_occurrences', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('plan_id')->constrained('plans')->restrictOnDelete();
            $table->foreignId('schedule_rule_id')->constrained('plan_schedule_rules')->restrictOnDelete();
            $table->date('local_date');
            $table->dateTime('scheduled_start_at');
            $table->dateTime('scheduled_end_at');
            $table->dateTime('window_start_at');
            $table->dateTime('window_end_at');
            $table->string('timezone', 64);
            $table->string('status', 24)->default('scheduled');
            $table->dateTime('actual_start_at')->nullable();
            $table->dateTime('actual_end_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->string('origin_type', 80)->nullable();
            $table->uuid('origin_uuid')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(
                ['schedule_rule_id', 'scheduled_start_at'],
                'plan_occurrences_rule_start_unique',
            );
            $table->index(['plan_id', 'scheduled_start_at'], 'plan_occurrences_plan_start_index');
            $table->index(['status', 'scheduled_start_at'], 'plan_occurrences_status_start_index');
            $table->index(['origin_type', 'origin_uuid'], 'plan_occurrences_origin_index');
        });

        Schema::create('plan_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('plan_id')->constrained('plans')->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('actors')->restrictOnDelete();
            $table->string('event_type', 40);
            $table->json('payload')->nullable();
            $table->dateTime('created_at');

            $table->index(['plan_id', 'created_at'], 'plan_events_plan_created_index');
        });

        Schema::create('plan_occurrence_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('plan_occurrence_id')->constrained('plan_occurrences')->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('actors')->restrictOnDelete();
            $table->string('event_type', 40);
            $table->json('payload')->nullable();
            $table->dateTime('created_at');

            $table->index(
                ['plan_occurrence_id', 'created_at'],
                'plan_occurrence_events_occurrence_created_index',
            );
        });

        Schema::create('plan_occurrence_assets', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('plan_occurrence_id')->constrained('plan_occurrences')->restrictOnDelete();
            $table->foreignId('asset_id')->constrained('assets')->restrictOnDelete();
            $table->foreignId('added_by_actor_id')->constrained('actors')->restrictOnDelete();
            $table->timestamps();

            $table->unique(
                ['plan_occurrence_id', 'asset_id'],
                'plan_occurrence_assets_unique',
            );
        });

        Schema::create('plan_occurrence_evidence_references', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('plan_occurrence_id')->constrained('plan_occurrences')->restrictOnDelete();
            $table->foreignId('content_evidence_reference_id')->constrained('content_evidence_references')->restrictOnDelete();
            $table->foreignId('added_by_actor_id')->constrained('actors')->restrictOnDelete();
            $table->timestamps();

            $table->unique(
                ['plan_occurrence_id', 'content_evidence_reference_id'],
                'plan_occurrence_evidence_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_occurrence_evidence_references');
        Schema::dropIfExists('plan_occurrence_assets');
        Schema::dropIfExists('plan_occurrence_events');
        Schema::dropIfExists('plan_events');
        Schema::dropIfExists('plan_occurrences');
        Schema::dropIfExists('plan_reminders');
        Schema::dropIfExists('plan_schedule_rules');
        Schema::dropIfExists('plan_participants');
        Schema::dropIfExists('plans');
    }
};
