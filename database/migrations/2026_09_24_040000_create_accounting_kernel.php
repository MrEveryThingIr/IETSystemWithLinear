<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Monetary units. The ledger/journal tables this migration used to create were
 * retired with the legacy financial system (see
 * 2026_10_19_000000_retire_legacy_financial_system).
 */
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
    }

    public function down(): void
    {
        Schema::dropIfExists('monetary_units');
    }
};
