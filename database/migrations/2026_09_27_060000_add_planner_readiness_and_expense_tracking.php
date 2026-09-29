<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_prerequisites', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('plan_id')->constrained('plans')->restrictOnDelete();
            $table->foreignId('created_by_actor_id')->constrained('actors')->restrictOnDelete();
            $table->string('title', 240);
            $table->boolean('is_required')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['plan_id', 'sort_order'], 'plan_prerequisites_plan_sort_index');
        });

        Schema::create('plan_occurrence_prerequisite_checks', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('plan_occurrence_id');
            $table->foreignId('plan_prerequisite_id');
            $table->foreignId('completed_by_actor_id')->nullable();
            $table->foreign('plan_occurrence_id', 'popc_occurrence_fk')
                ->references('id')->on('plan_occurrences')->restrictOnDelete();
            $table->foreign('plan_prerequisite_id', 'popc_prerequisite_fk')
                ->references('id')->on('plan_prerequisites')->restrictOnDelete();
            $table->foreign('completed_by_actor_id', 'popc_completed_actor_fk')
                ->references('id')->on('actors')->restrictOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['plan_occurrence_id', 'plan_prerequisite_id'],
                'plan_occurrence_prerequisite_unique',
            );
        });

        Schema::create('plan_expense_estimates', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('plan_id')->constrained('plans')->restrictOnDelete();
            $table->foreignId('created_by_actor_id')->constrained('actors')->restrictOnDelete();
            $table->foreignId('monetary_unit_id')->constrained('monetary_units')->restrictOnDelete();
            $table->string('label', 180);
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['plan_id', 'sort_order'], 'plan_expense_estimates_plan_sort_index');
        });

        Schema::create('plan_occurrence_expenses', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('plan_occurrence_id')->constrained('plan_occurrences')->restrictOnDelete();
            $table->foreignId('plan_expense_estimate_id')->nullable()
                ->constrained('plan_expense_estimates')->restrictOnDelete();
            $table->foreignId('monetary_unit_id')->constrained('monetary_units')->restrictOnDelete();
            $table->foreignId('created_by_actor_id')->constrained('actors')->restrictOnDelete();
            $table->string('label', 180);
            $table->unsignedBigInteger('amount_minor');
            $table->string('note', 500)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['plan_occurrence_id', 'occurred_at'], 'plan_occurrence_expenses_occurrence_time_index');
            $table->index('plan_expense_estimate_id', 'plan_occurrence_expenses_estimate_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_occurrence_expenses');
        Schema::dropIfExists('plan_expense_estimates');
        Schema::dropIfExists('plan_occurrence_prerequisite_checks');
        Schema::dropIfExists('plan_prerequisites');
    }
};
