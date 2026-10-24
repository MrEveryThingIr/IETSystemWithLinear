<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * v1.10 — reusable plan shapes (no people, no dates, no history) and where
 * every bookkeeping entry came from. Additive; existing entries are marked
 * "manual" (or "correction" for adjustments) and keep everything they had.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_templates', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('owner_actor_id')->nullable()->constrained('actors')->restrictOnDelete();
            $table->foreignId('owner_business_id')->nullable()->constrained('businesses')->restrictOnDelete();
            $table->foreignId('owner_group_id')->nullable()->constrained('groups')->restrictOnDelete();
            $table->string('name', 160);
            $table->string('frequency', 24);
            $table->string('timing_mode', 24)->default('fixed');
            $table->unsignedSmallInteger('interval')->default(1);
            $table->string('start_time', 8)->default('09:00');
            $table->unsignedInteger('duration_minutes')->default(60);
            $table->json('weekdays')->nullable();
            $table->unsignedTinyInteger('month_day')->nullable();
            $table->unsignedInteger('occurrence_limit')->nullable();
            $table->unsignedInteger('window_before_minutes')->default(0);
            $table->unsignedInteger('window_after_minutes')->default(0);
            $table->json('reminder_offsets')->nullable();
            $table->string('status', 16)->default('active');
            $table->foreignId('created_by_actor_id')->constrained('actors')->restrictOnDelete();
            $table->string('source_plan_uuid', 36)->nullable();
            $table->timestamps();

            $table->index(['owner_actor_id', 'status']);
            $table->index(['owner_business_id', 'status']);
            $table->index(['owner_group_id', 'status']);
        });

        Schema::table('bk_transactions', function (Blueprint $table): void {
            $table->string('origin', 16)->default('manual')->after('status');
            $table->string('source_type', 40)->nullable()->after('origin');
            $table->string('source_uuid', 36)->nullable()->after('source_type');
            $table->unsignedInteger('source_version')->nullable()->after('source_uuid');

            $table->index(['ledger_id', 'origin'], 'bk_tx_ledger_origin_idx');
            $table->index(['source_type', 'source_uuid'], 'bk_tx_source_idx');
        });

        DB::table('bk_transactions')->where('type', 'adjustment')->update(['origin' => 'correction']);
    }

    public function down(): void
    {
        Schema::table('bk_transactions', function (Blueprint $table): void {
            $table->dropIndex('bk_tx_ledger_origin_idx');
            $table->dropIndex('bk_tx_source_idx');
        });
        Schema::table('bk_transactions', fn (Blueprint $table) => $table->dropColumn(['origin', 'source_type', 'source_uuid', 'source_version']));
        Schema::dropIfExists('plan_templates');
    }
};
