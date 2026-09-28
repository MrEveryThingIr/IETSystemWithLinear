<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iet_valuation_quotes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->decimal('usd_per_iet', 24, 10);
            $table->string('policy_version', 80)->default('manual-v1');
            $table->json('factors')->nullable();
            $table->text('rationale')->nullable();
            $table->timestamp('effective_at');
            $table->foreignId('published_by_user_id')->nullable()
                ->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['effective_at', 'id']);
        });

        Schema::create('iet_exchange_requests', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('direction', 24);
            $table->string('external_unit_code', 12)->default('USD');
            $table->unsignedBigInteger('external_amount_minor');
            $table->foreignId('valuation_quote_id')
                ->constrained('iet_valuation_quotes')->restrictOnDelete();
            $table->unsignedBigInteger('iet_amount');
            $table->string('status', 32)->default('pending');
            $table->string('external_reference', 255)->nullable();
            $table->text('note')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()
                ->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->foreignId('journal_entry_id')->nullable()
                ->constrained('journal_entries')->restrictOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'status', 'id']);
            $table->index(['status', 'id']);
        });

        Schema::create('iet_internal_charges', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('source_type', 80);
            $table->uuid('source_uuid');
            $table->unsignedBigInteger('usd_amount_minor');
            $table->foreignId('valuation_quote_id')
                ->constrained('iet_valuation_quotes')->restrictOnDelete();
            $table->unsignedBigInteger('iet_amount');
            $table->foreignId('journal_entry_id')
                ->constrained('journal_entries')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'source_type', 'source_uuid'], 'iet_internal_charges_user_source_unique');
        });

        DB::table('iet_valuation_quotes')->insert([
            'uuid' => (string) Str::uuid(),
            'usd_per_iet' => '0.0000010000',
            'policy_version' => 'initial-v1',
            'factors' => json_encode([
                'basis' => 'initial baseline',
                'automatic_growth' => false,
            ], JSON_THROW_ON_ERROR),
            'rationale' => 'Initial IET internal settlement valuation.',
            'effective_at' => now(),
            'published_by_user_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('iet_internal_charges');
        Schema::dropIfExists('iet_exchange_requests');
        Schema::dropIfExists('iet_valuation_quotes');
    }
};
