<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_service_terms', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('contract_version_id')->unique();
            $table->foreignId('employer_actor_id');
            $table->foreignId('worker_actor_id');
            $table->foreignId('monetary_unit_id');
            $table->string('service_title', 180);
            $table->string('service_kind', 32)->default('service');
            $table->decimal('total_quantity', 18, 4);
            $table->decimal('quantity_per_occurrence', 18, 4)->default(1);
            $table->string('unit', 40);
            $table->unsignedBigInteger('unit_rate_minor');
            $table->string('settlement_cycle', 32)->default('per_fulfillment');
            $table->unsignedSmallInteger('payment_due_days')->default(0);
            $table->boolean('auto_create_plan')->default(true);
            $table->boolean('auto_recognize_obligation')->default(true);
            $table->string('plan_frequency', 32)->default('once');
            $table->date('plan_starts_on');
            $table->string('plan_start_time', 8);
            $table->unsignedInteger('plan_duration_minutes');
            $table->unsignedSmallInteger('plan_interval')->default(1);
            $table->json('plan_weekdays')->nullable();
            $table->json('plan_selected_dates')->nullable();
            $table->date('plan_ends_on')->nullable();
            $table->unsignedInteger('plan_occurrence_limit')->nullable();
            $table->unsignedInteger('window_before_minutes')->default(0);
            $table->unsignedInteger('window_after_minutes')->default(0);
            $table->json('reminder_offsets')->nullable();
            $table->string('timezone', 64);
            $table->timestamps();

            $table->foreign('contract_version_id', 'contract_service_terms_version_fk')
                ->references('id')->on('contract_versions')->restrictOnDelete();
            $table->foreign('employer_actor_id', 'contract_service_terms_employer_fk')
                ->references('id')->on('actors')->restrictOnDelete();
            $table->foreign('worker_actor_id', 'contract_service_terms_worker_fk')
                ->references('id')->on('actors')->restrictOnDelete();
            $table->foreign('monetary_unit_id', 'contract_service_terms_unit_fk')
                ->references('id')->on('monetary_units')->restrictOnDelete();

            $table->index(['employer_actor_id', 'worker_actor_id'], 'contract_service_terms_parties_index');
        });

        Schema::table('commitments', function (Blueprint $table): void {
            $table->foreignId('contract_service_term_id')->nullable()->unique()->after('contract_version_id');

            $table->foreign('contract_service_term_id', 'commitments_service_term_fk')
                ->references('id')->on('contract_service_terms')->restrictOnDelete();
        });

        Schema::create('contract_settlement_batches', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('contract_id');
            $table->foreignId('debtor_actor_id');
            $table->foreignId('creditor_actor_id');
            $table->foreignId('monetary_unit_id');
            $table->foreignId('proposed_by_actor_id');
            $table->unsignedBigInteger('amount_minor');
            $table->string('method', 32)->default('cash');
            $table->timestamp('paid_at');
            $table->string('reference', 255)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->foreign('contract_id', 'settlement_batches_contract_fk')
                ->references('id')->on('contracts')->restrictOnDelete();
            $table->foreign('debtor_actor_id', 'settlement_batches_debtor_fk')
                ->references('id')->on('actors')->restrictOnDelete();
            $table->foreign('creditor_actor_id', 'settlement_batches_creditor_fk')
                ->references('id')->on('actors')->restrictOnDelete();
            $table->foreign('monetary_unit_id', 'settlement_batches_unit_fk')
                ->references('id')->on('monetary_units')->restrictOnDelete();
            $table->foreign('proposed_by_actor_id', 'settlement_batches_proposer_fk')
                ->references('id')->on('actors')->restrictOnDelete();

            $table->index(['contract_id', 'monetary_unit_id', 'paid_at'], 'settlement_batches_contract_unit_time_index');
        });

        Schema::table('settlements', function (Blueprint $table): void {
            $table->foreignId('contract_settlement_batch_id')->nullable()->after('financial_obligation_id');

            $table->foreign('contract_settlement_batch_id', 'settlements_batch_fk')
                ->references('id')->on('contract_settlement_batches')->restrictOnDelete();
            $table->index('contract_settlement_batch_id', 'settlements_batch_index');
        });
    }

    public function down(): void
    {
        Schema::table('settlements', function (Blueprint $table): void {
            $table->dropForeign(['contract_settlement_batch_id']);
            $table->dropIndex('settlements_batch_index');
            $table->dropColumn('contract_settlement_batch_id');
        });

        Schema::dropIfExists('contract_settlement_batches');

        Schema::table('commitments', function (Blueprint $table): void {
            $table->dropForeign(['contract_service_term_id']);
            $table->dropUnique(['contract_service_term_id']);
            $table->dropColumn('contract_service_term_id');
        });

        Schema::dropIfExists('contract_service_terms');
    }
};
