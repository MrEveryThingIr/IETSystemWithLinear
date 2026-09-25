<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_entries', function (Blueprint $table): void {
            $table->string('source_type', 80)->nullable()->after('description');
            $table->uuid('source_uuid')->nullable()->after('source_type');
            $table->string('idempotency_key', 190)->nullable()->after('source_uuid');
            $table->foreignId('acting_user_id')->nullable()
                ->after('created_by_actor_id')
                ->constrained('users')->restrictOnDelete();

            $table->index(['source_type', 'source_uuid'], 'journal_entries_source_index');
            $table->unique(['ledger_id', 'idempotency_key'], 'journal_entries_ledger_idempotency_unique');
        });

        Schema::create('financial_obligations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('fulfillment_id')->unique()
                ->constrained('fulfillments')->restrictOnDelete();
            $table->foreignId('contract_version_id')
                ->constrained('contract_versions')->restrictOnDelete();
            $table->foreignId('debtor_actor_id')
                ->constrained('actors')->restrictOnDelete();
            $table->foreignId('creditor_actor_id')
                ->constrained('actors')->restrictOnDelete();
            $table->foreignId('monetary_unit_id')
                ->constrained('monetary_units')->restrictOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->string('description', 500)->nullable();
            $table->timestamp('due_at')->nullable();
            $table->foreignId('recognized_by_actor_id')
                ->constrained('actors')->restrictOnDelete();
            $table->timestamp('recognized_at');
            $table->timestamps();

            $table->index(['contract_version_id', 'monetary_unit_id'], 'financial_obligations_contract_unit_index');
            $table->index(['debtor_actor_id', 'monetary_unit_id'], 'financial_obligations_debtor_unit_index');
            $table->index(['creditor_actor_id', 'monetary_unit_id'], 'financial_obligations_creditor_unit_index');
        });

        Schema::create('settlements', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('financial_obligation_id')
                ->constrained('financial_obligations')->restrictOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->timestamp('paid_at');
            $table->string('method', 80)->nullable();
            $table->string('reference', 255)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('proposed_by_actor_id')
                ->constrained('actors')->restrictOnDelete();
            $table->string('status', 32)->default('pending_confirmation');
            $table->foreignId('confirmed_by_actor_id')->nullable()
                ->constrained('actors')->restrictOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('rejected_by_actor_id')->nullable()
                ->constrained('actors')->restrictOnDelete();
            $table->text('rejection_note')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();

            $table->index(['financial_obligation_id', 'status'], 'settlements_obligation_status_index');
        });

        Schema::create('financial_obligation_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('financial_obligation_id')
                ->constrained('financial_obligations')->restrictOnDelete();
            $table->foreignId('settlement_id')->nullable()
                ->constrained('settlements')->restrictOnDelete();
            $table->foreignId('journal_entry_id')->nullable()->unique()
                ->constrained('journal_entries')->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()
                ->constrained('actors')->restrictOnDelete();
            $table->string('event_type', 48);
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['financial_obligation_id', 'id'], 'financial_obligation_events_obligation_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_obligation_events');
        Schema::dropIfExists('settlements');
        Schema::dropIfExists('financial_obligations');

        Schema::table('journal_entries', function (Blueprint $table): void {
            $table->dropUnique('journal_entries_ledger_idempotency_unique');
            $table->dropIndex('journal_entries_source_index');
            $table->dropConstrainedForeignId('acting_user_id');
            $table->dropColumn(['source_type', 'source_uuid', 'idempotency_key']);
        });
    }
};
