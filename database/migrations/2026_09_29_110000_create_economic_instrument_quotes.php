<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Retired with the legacy financial system (accounting, obligations,
 * settlements, IET exchange/valuation). Kept as an empty step so migration
 * history stays consistent; existing databases drop these tables in
 * 2026_10_19_000000_retire_legacy_financial_system.
 */
return new class extends Migration
{
    public function up(): void {}

    public function down(): void {}
};
