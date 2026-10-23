<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planner_experiments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('owner_actor_id')->constrained('actors')->restrictOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('plans')->restrictOnDelete();
            $table->foreignId('purpose_id')->nullable()->constrained('planner_purposes')->restrictOnDelete();
            $table->string('title', 180);
            $table->text('hypothesis');
            $table->text('selected_change');
            $table->string('metric', 32);
            $table->decimal('baseline', 14, 3)->nullable();
            $table->decimal('target', 14, 3)->nullable();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('timezone', 80);
            $table->text('cue')->nullable();
            $table->text('planned_response')->nullable();
            $table->dateTime('created_at');
            $table->index(['owner_actor_id', 'starts_on'], 'planner_experiment_owner_period_idx');
        });
        Schema::create('planner_experiment_observations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('experiment_id')->constrained('planner_experiments')->restrictOnDelete();
            $table->foreignId('supersedes_id')->nullable()->constrained('planner_experiment_observations')->restrictOnDelete();
            $table->string('retry_key', 100);
            $table->char('fingerprint', 64);
            $table->dateTime('observed_at');
            $table->decimal('value', 14, 3)->nullable();
            $table->unsignedTinyInteger('difficulty')->nullable();
            $table->text('cue')->nullable();
            $table->text('obstacle')->nullable();
            $table->text('response')->nullable();
            $table->text('reflection')->nullable();
            $table->json('context')->nullable();
            $table->dateTime('created_at');
            $table->unique(['experiment_id', 'retry_key'], 'experiment_observation_retry_unique');
            $table->unique('supersedes_id', 'experiment_observation_correction_unique');
        });
        Schema::create('planner_experiment_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('experiment_id')->constrained('planner_experiments')->restrictOnDelete();
            $table->text('proposal');
            $table->string('decision', 16)->nullable();
            $table->dateTime('decided_at')->nullable();
            $table->dateTime('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planner_experiment_adjustments');
        Schema::dropIfExists('planner_experiment_observations');
        Schema::dropIfExists('planner_experiments');
    }
};
