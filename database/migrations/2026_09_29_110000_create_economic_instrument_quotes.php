<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('economic_instruments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code', 48)->unique();
            $table->string('name', 180);
            $table->string('kind', 40);
            $table->foreignId('monetary_unit_id')->nullable()->unique()
                ->constrained('monetary_units')->restrictOnDelete();
            $table->boolean('settlement_enabled')->default(false);
            $table->boolean('active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['kind', 'active']);
        });

        Schema::create('quote_sources', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('key', 80)->unique();
            $table->string('name', 180);
            $table->string('source_type', 32)->default('manual');
            $table->unsignedTinyInteger('trust_tier')->default(1);
            $table->boolean('active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['active', 'trust_tier']);
        });

        Schema::create('market_quotes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('base_instrument_id')
                ->constrained('economic_instruments')->restrictOnDelete();
            $table->foreignId('quote_instrument_id')
                ->constrained('economic_instruments')->restrictOnDelete();
            $table->decimal('price', 50, 20);
            $table->decimal('bid', 50, 20)->nullable();
            $table->decimal('ask', 50, 20)->nullable();
            $table->foreignId('quote_source_id')
                ->constrained('quote_sources')->restrictOnDelete();
            $table->string('source_reference', 255)->nullable();
            $table->timestamp('observed_at');
            $table->timestamp('effective_at');
            $table->timestamp('expires_at')->nullable();
            $table->unsignedSmallInteger('confidence_bps')->nullable();
            $table->foreignId('published_by_user_id')->nullable()
                ->constrained('users')->restrictOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(
                ['base_instrument_id', 'quote_instrument_id', 'effective_at', 'id'],
                'market_quotes_pair_effective_index',
            );
            $table->index(['quote_source_id', 'observed_at'], 'market_quotes_source_observed_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_quotes');
        Schema::dropIfExists('quote_sources');
        Schema::dropIfExists('economic_instruments');
    }
};
