<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bookkeeping: personal, shared and project books in toman.
 *
 * bk_ledgers            a book; each has its own Context (evidence, chat).
 * bk_ledger_parties     people in a book: users or name-only (accountless)
 *                       Actors, with a role.
 * bk_wallets            where money sits for the owner of a personal book
 *                       (cash, card, bank); private to that person.
 * bk_categories         income / expense categories of a personal book.
 * bk_transactions       every recorded fact: a debt (obligation), payment,
 *                       refund, write-off, settlement in kind, adjustment,
 *                       and personal income / expense / transfer.
 * bk_allocations        which debts a payment (or refund, write-off, in-kind
 *                       settlement) applies to, and how much to each.
 * bk_confirmations      each party's approval or rejection.
 * bk_disputes           a formal objection to a confirmed entry.
 * bk_transaction_assets evidence files (receipts, screenshots).
 * bk_audit_logs         append-only history of every change.
 *
 * Amounts are whole toman in unsigned big integers. Balances are never
 * stored; they are always calculated from confirmed entries.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bk_ledgers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('bk_ledgers_uuid_uq');
            $table->string('kind', 16);
            $table->string('name', 160);
            $table->string('description', 1000)->nullable();
            $table->string('currency', 8)->default('IRT');
            $table->foreignId('context_id');
            $table->foreignId('owner_actor_id');
            $table->string('status', 16)->default('active');
            $table->dateTime('archived_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('context_id', 'bk_ledgers_context_fk')->references('id')->on('contexts')->restrictOnDelete();
            $table->foreign('owner_actor_id', 'bk_ledgers_owner_fk')->references('id')->on('actors')->restrictOnDelete();
            $table->unique('context_id', 'bk_ledgers_context_uq');
            $table->index(['owner_actor_id', 'kind', 'status'], 'bk_ledgers_owner_kind_idx');
        });

        Schema::create('bk_ledger_parties', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('bk_parties_uuid_uq');
            $table->foreignId('ledger_id');
            $table->foreignId('actor_id');
            $table->string('display_name', 120)->nullable();
            $table->string('contact_hint', 120)->nullable();
            $table->string('role', 16);
            $table->string('status', 16)->default('active');
            $table->foreignId('added_by_actor_id');
            $table->dateTime('joined_at');
            $table->dateTime('left_at')->nullable();
            $table->timestamps();

            $table->foreign('ledger_id', 'bk_parties_ledger_fk')->references('id')->on('bk_ledgers')->restrictOnDelete();
            $table->foreign('actor_id', 'bk_parties_actor_fk')->references('id')->on('actors')->restrictOnDelete();
            $table->foreign('added_by_actor_id', 'bk_parties_added_by_fk')->references('id')->on('actors')->restrictOnDelete();
            $table->unique(['ledger_id', 'actor_id'], 'bk_parties_ledger_actor_uq');
            $table->index(['actor_id', 'status'], 'bk_parties_actor_status_idx');
        });

        Schema::create('bk_wallets', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('bk_wallets_uuid_uq');
            $table->foreignId('ledger_id');
            $table->string('name', 120);
            $table->string('kind', 16);
            $table->string('masked_identifier', 40)->nullable();
            $table->unsignedBigInteger('opening_balance')->default(0);
            $table->dateTime('archived_at')->nullable();
            $table->timestamps();

            $table->foreign('ledger_id', 'bk_wallets_ledger_fk')->references('id')->on('bk_ledgers')->restrictOnDelete();
            $table->index(['ledger_id', 'archived_at'], 'bk_wallets_ledger_idx');
        });

        Schema::create('bk_categories', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('bk_categories_uuid_uq');
            $table->foreignId('ledger_id');
            $table->string('direction', 16);
            $table->string('name', 80);
            $table->string('system_key', 40)->nullable();
            $table->dateTime('archived_at')->nullable();
            $table->timestamps();

            $table->foreign('ledger_id', 'bk_categories_ledger_fk')->references('id')->on('bk_ledgers')->restrictOnDelete();
            $table->unique(['ledger_id', 'direction', 'name'], 'bk_categories_name_uq');
        });

        Schema::create('bk_transactions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('bk_transactions_uuid_uq');
            $table->foreignId('ledger_id');
            $table->string('type', 24);
            $table->string('status', 24);
            $table->unsignedBigInteger('amount');
            $table->string('direction', 16)->nullable();
            $table->date('occurred_on');
            $table->date('due_on')->nullable();
            $table->string('description', 500)->nullable();
            $table->foreignId('from_party_id')->nullable();
            $table->foreignId('to_party_id')->nullable();
            $table->foreignId('wallet_id')->nullable();
            $table->foreignId('counter_wallet_id')->nullable();
            $table->foreignId('category_id')->nullable();
            $table->string('counterparty_name', 120)->nullable();
            $table->foreignId('related_transaction_id')->nullable();
            $table->foreignId('recorded_by_actor_id');
            $table->foreignId('on_behalf_of_actor_id')->nullable();
            $table->string('idempotency_key', 80)->nullable();
            $table->dateTime('confirmed_at')->nullable();
            $table->dateTime('voided_at')->nullable();
            $table->string('void_reason', 500)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('ledger_id', 'bk_tx_ledger_fk')->references('id')->on('bk_ledgers')->restrictOnDelete();
            $table->foreign('from_party_id', 'bk_tx_from_fk')->references('id')->on('bk_ledger_parties')->restrictOnDelete();
            $table->foreign('to_party_id', 'bk_tx_to_fk')->references('id')->on('bk_ledger_parties')->restrictOnDelete();
            $table->foreign('wallet_id', 'bk_tx_wallet_fk')->references('id')->on('bk_wallets')->restrictOnDelete();
            $table->foreign('counter_wallet_id', 'bk_tx_counter_wallet_fk')->references('id')->on('bk_wallets')->restrictOnDelete();
            $table->foreign('category_id', 'bk_tx_category_fk')->references('id')->on('bk_categories')->restrictOnDelete();
            $table->foreign('related_transaction_id', 'bk_tx_related_fk')->references('id')->on('bk_transactions')->restrictOnDelete();
            $table->foreign('recorded_by_actor_id', 'bk_tx_recorder_fk')->references('id')->on('actors')->restrictOnDelete();
            $table->foreign('on_behalf_of_actor_id', 'bk_tx_on_behalf_fk')->references('id')->on('actors')->restrictOnDelete();
            $table->unique(['recorded_by_actor_id', 'idempotency_key'], 'bk_tx_idempotency_uq');
            $table->index(['ledger_id', 'status'], 'bk_tx_ledger_status_idx');
            $table->index(['ledger_id', 'occurred_on'], 'bk_tx_ledger_date_idx');
            $table->index(['from_party_id', 'to_party_id'], 'bk_tx_parties_idx');
            $table->index(['wallet_id', 'status'], 'bk_tx_wallet_idx');
        });

        Schema::create('bk_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('transaction_id');
            $table->foreignId('obligation_id');
            $table->unsignedBigInteger('amount');
            $table->timestamps();

            $table->foreign('transaction_id', 'bk_alloc_tx_fk')->references('id')->on('bk_transactions')->cascadeOnDelete();
            $table->foreign('obligation_id', 'bk_alloc_obligation_fk')->references('id')->on('bk_transactions')->restrictOnDelete();
            $table->unique(['transaction_id', 'obligation_id'], 'bk_alloc_pair_uq');
            $table->index('obligation_id', 'bk_alloc_obligation_idx');
        });

        Schema::create('bk_confirmations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('transaction_id');
            $table->foreignId('party_id');
            $table->foreignId('actor_id');
            $table->foreignId('on_behalf_of_actor_id')->nullable();
            $table->string('decision', 16);
            $table->string('note', 1000)->nullable();
            $table->dateTime('decided_at');
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();

            $table->foreign('transaction_id', 'bk_conf_tx_fk')->references('id')->on('bk_transactions')->cascadeOnDelete();
            $table->foreign('party_id', 'bk_conf_party_fk')->references('id')->on('bk_ledger_parties')->restrictOnDelete();
            $table->foreign('actor_id', 'bk_conf_actor_fk')->references('id')->on('actors')->restrictOnDelete();
            $table->foreign('on_behalf_of_actor_id', 'bk_conf_on_behalf_fk')->references('id')->on('actors')->restrictOnDelete();
            $table->unique(['transaction_id', 'party_id'], 'bk_conf_tx_party_uq');
        });

        Schema::create('bk_disputes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('bk_disputes_uuid_uq');
            $table->foreignId('transaction_id');
            $table->foreignId('raised_by_actor_id');
            $table->string('reason', 1000);
            $table->string('status', 16);
            $table->string('outcome', 16)->nullable();
            $table->string('resolution_note', 1000)->nullable();
            $table->foreignId('resolved_by_actor_id')->nullable();
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();

            $table->foreign('transaction_id', 'bk_disputes_tx_fk')->references('id')->on('bk_transactions')->restrictOnDelete();
            $table->foreign('raised_by_actor_id', 'bk_disputes_raiser_fk')->references('id')->on('actors')->restrictOnDelete();
            $table->foreign('resolved_by_actor_id', 'bk_disputes_resolver_fk')->references('id')->on('actors')->restrictOnDelete();
            $table->index(['transaction_id', 'status'], 'bk_disputes_tx_status_idx');
        });

        Schema::create('bk_transaction_assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('transaction_id');
            $table->foreignId('asset_id');
            $table->foreignId('added_by_actor_id');
            $table->string('kind', 24)->default('receipt');
            $table->timestamps();

            $table->foreign('transaction_id', 'bk_tx_assets_tx_fk')->references('id')->on('bk_transactions')->cascadeOnDelete();
            $table->foreign('asset_id', 'bk_tx_assets_asset_fk')->references('id')->on('assets')->restrictOnDelete();
            $table->foreign('added_by_actor_id', 'bk_tx_assets_actor_fk')->references('id')->on('actors')->restrictOnDelete();
            $table->unique(['transaction_id', 'asset_id'], 'bk_tx_assets_uq');
        });

        Schema::create('bk_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ledger_id');
            $table->foreignId('transaction_id')->nullable();
            $table->foreignId('actor_id');
            $table->foreignId('on_behalf_of_actor_id')->nullable();
            $table->string('action', 40);
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->dateTime('created_at');

            $table->foreign('ledger_id', 'bk_audit_ledger_fk')->references('id')->on('bk_ledgers')->restrictOnDelete();
            $table->foreign('transaction_id', 'bk_audit_tx_fk')->references('id')->on('bk_transactions')->restrictOnDelete();
            $table->foreign('actor_id', 'bk_audit_actor_fk')->references('id')->on('actors')->restrictOnDelete();
            $table->foreign('on_behalf_of_actor_id', 'bk_audit_on_behalf_fk')->references('id')->on('actors')->restrictOnDelete();
            $table->index(['ledger_id', 'created_at'], 'bk_audit_ledger_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bk_audit_logs');
        Schema::dropIfExists('bk_transaction_assets');
        Schema::dropIfExists('bk_disputes');
        Schema::dropIfExists('bk_confirmations');
        Schema::dropIfExists('bk_allocations');
        Schema::dropIfExists('bk_transactions');
        Schema::dropIfExists('bk_categories');
        Schema::dropIfExists('bk_wallets');
        Schema::dropIfExists('bk_ledger_parties');
        Schema::dropIfExists('bk_ledgers');
    }
};
