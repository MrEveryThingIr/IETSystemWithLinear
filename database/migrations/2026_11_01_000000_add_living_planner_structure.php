<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->foreignId('parent_plan_id')->nullable()->constrained('plans')->restrictOnDelete();
            $table->string('structure_kind', 20)->default('activity');
        });
        Schema::create('planner_purposes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('owner_actor_id')->constrained('actors')->restrictOnDelete();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->date('target_on')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->index(['owner_actor_id', 'status'], 'purpose_owner_status_idx');
        });
        Schema::create('planner_purpose_contributions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purpose_id')->constrained('planner_purposes')->restrictOnDelete();
            $table->foreignId('plan_id')->constrained('plans')->restrictOnDelete();
            $table->foreignId('linked_by_actor_id')->constrained('actors')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['purpose_id', 'plan_id'], 'purpose_plan_unique');
        });
        Schema::table('plan_templates', function (Blueprint $table): void {
            $table->json('composition')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('plan_templates', fn (Blueprint $table) => $table->dropColumn('composition'));
        Schema::dropIfExists('planner_purpose_contributions');
        Schema::dropIfExists('planner_purposes');
        Schema::table('plans', function (Blueprint $table): void {
            $table->dropForeign(['parent_plan_id']);
            $table->dropColumn(['parent_plan_id', 'structure_kind']);
        });
    }
};
