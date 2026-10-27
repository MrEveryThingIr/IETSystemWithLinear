<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A Commitment set up from an Agreement duty remembers where it came from (accepted version : duty), so confirming the
 * same duty twice can never create a second Commitment. Written at creation only; older rows stay null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commitments', function (Blueprint $table): void {
            $table->string('derivation_key', 120)->nullable()->unique()->after('contract_service_term_id');
        });
    }

    public function down(): void
    {
        Schema::table('commitments', function (Blueprint $table): void {
            $table->dropUnique(['derivation_key']);
            $table->dropColumn('derivation_key');
        });
    }
};
