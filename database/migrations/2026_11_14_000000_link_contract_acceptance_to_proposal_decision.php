<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_acceptances', function (Blueprint $table): void {
            // Set when the acceptance was derived from the party's ProposalDecision on the same Terms (single Agree step).
            $table->foreignId('source_proposal_decision_id')->nullable()->unique()->after('contract_version_party_id')
                ->constrained('proposal_decisions')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('contract_acceptances', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('source_proposal_decision_id');
        });
    }
};
