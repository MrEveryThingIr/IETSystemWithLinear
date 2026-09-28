<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iet_rate_versions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedInteger('sequence')->unique();
            $table->unsignedBigInteger('usd_numerator');
            $table->unsignedBigInteger('usd_denominator');
            $table->integer('adjustment_ppm')->nullable();
            $table->string('reason', 500);
            $table->json('criteria')->nullable();
            $table->timestamp('effective_at');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['effective_at', 'sequence']);
        });

        Schema::create('iet_exchange_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('direction', 16);
            $table->foreignId('rate_version_id')->constrained('iet_rate_versions')->restrictOnDelete();
            $table->foreignId('fiat_unit_id')->constrained('monetary_units')->restrictOnDelete();
            $table->unsignedBigInteger('fiat_amount_minor');
            $table->unsignedBigInteger('iet_amount_minor');
            $table->string('status', 24)->default('pending');
            $table->string('external_reference', 255)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('rejection_note')->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'status', 'created_at']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('iet_internal_transfers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('sender_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('receiver_user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('iet_amount_minor');
            $table->foreignId('rate_version_id')->nullable()->constrained('iet_rate_versions')->restrictOnDelete();
            $table->unsignedBigInteger('usd_reference_minor')->nullable();
            $table->string('source_type', 80)->nullable();
            $table->uuid('source_uuid')->nullable();
            $table->foreignId('sender_journal_entry_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
            $table->foreignId('receiver_journal_entry_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
            $table->string('description', 500)->nullable();
            $table->timestamps();

            $table->index(['sender_user_id', 'created_at']);
            $table->index(['receiver_user_id', 'created_at']);
            $table->unique(['source_type', 'source_uuid'], 'iet_internal_transfer_source_unique');
        });

        Schema::create('iet_flow_charges', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rate_version_id')->constrained('iet_rate_versions')->restrictOnDelete();
            $table->unsignedBigInteger('usd_reference_minor');
            $table->unsignedBigInteger('iet_amount_minor');
            $table->string('source_type', 80);
            $table->uuid('source_uuid');
            $table->string('description', 500)->nullable();
            $table->foreignId('journal_entry_id')->constrained('journal_entries')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'source_type', 'source_uuid'], 'iet_flow_charge_source_unique');
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iet_flow_charges');
        Schema::dropIfExists('iet_internal_transfers');
        Schema::dropIfExists('iet_exchange_requests');
        Schema::dropIfExists('iet_rate_versions');
    }
};
