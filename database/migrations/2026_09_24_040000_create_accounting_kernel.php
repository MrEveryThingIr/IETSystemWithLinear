<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monetary_units', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code', 12)->unique();
            $table->string('name', 120);
            $table->string('symbol', 16)->nullable();
            $table->unsignedTinyInteger('exponent')->default(2);
            $table->timestamps();
        });

        Schema::create('ledgers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('context_id')->constrained('contexts')->restrictOnDelete();
            $table->foreignId('monetary_unit_id')->constrained('monetary_units')->restrictOnDelete();
            $table->string('key', 80)->default('main');
            $table->string('name', 180);
            $table->string('status', 24)->default('active');
            $table->foreignId('created_by_actor_id')->constrained('actors')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['context_id', 'monetary_unit_id', 'key'], 'ledgers_context_unit_key_unique');
        });

        Schema::create('accounts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('ledger_id')->constrained('ledgers')->restrictOnDelete();
            $table->string('code', 80);
            $table->string('name', 180);
            $table->string('type', 24);
            $table->string('system_key', 80)->nullable();
            $table->string('status', 24)->default('active');
            $table->foreignId('created_by_actor_id')->constrained('actors')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['ledger_id', 'code'], 'accounts_ledger_code_unique');
            $table->unique(['ledger_id', 'system_key'], 'accounts_ledger_system_key_unique');
            $table->index(['ledger_id', 'type', 'status'], 'accounts_ledger_type_status_index');
        });

        Schema::create('journal_entries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('ledger_id')->constrained('ledgers')->restrictOnDelete();
            $table->string('kind', 32);
            $table->date('occurred_on');
            $table->string('description', 500)->nullable();
            $table->foreignId('reverses_entry_id')->nullable()
                ->constrained('journal_entries')->restrictOnDelete();
            $table->foreignId('correction_of_entry_id')->nullable()
                ->constrained('journal_entries')->restrictOnDelete();
            $table->foreignId('created_by_actor_id')->constrained('actors')->restrictOnDelete();
            $table->timestamp('posted_at');
            $table->timestamps();

            $table->unique('reverses_entry_id', 'journal_entries_reverses_unique');
            $table->index(['ledger_id', 'occurred_on', 'id'], 'journal_entries_ledger_date_index');
            $table->index('correction_of_entry_id');
        });

        Schema::create('journal_lines', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('journal_entry_id')->constrained('journal_entries')->restrictOnDelete();
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
            $table->unsignedBigInteger('debit_minor')->default(0);
            $table->unsignedBigInteger('credit_minor')->default(0);
            $table->string('memo', 500)->nullable();
            $table->timestamps();

            $table->index(['account_id', 'journal_entry_id'], 'journal_lines_account_entry_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('accounts');
        Schema::dropIfExists('ledgers');
        Schema::dropIfExists('monetary_units');
    }
};
