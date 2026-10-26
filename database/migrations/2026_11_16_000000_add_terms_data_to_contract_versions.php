<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An amendment is a new ContractVersion; it carries its own typed Terms (the same schema as a ProposalVersion) so the
 * Agreement keeps structured price, payment, time and completion data across versions. The first version of a Contract
 * reads the Terms of the accepted ProposalVersion it came from, so existing rows stay valid as they are.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_versions', function (Blueprint $table): void {
            $table->json('terms_data')->nullable()->after('note');
            $table->string('terms_hash', 64)->nullable()->after('terms_data');
        });
    }

    public function down(): void
    {
        Schema::table('contract_versions', function (Blueprint $table): void {
            $table->dropColumn(['terms_data', 'terms_hash']);
        });
    }
};
