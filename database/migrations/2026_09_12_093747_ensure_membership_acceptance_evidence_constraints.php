<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasForeignKey('membership_agreement_acceptances', 'membership_acceptance_source_foreign')) {
            Schema::table('membership_agreement_acceptances', function (Blueprint $table): void {
                $table->foreign('source_admission_acceptance_id', 'membership_acceptance_source_foreign')
                    ->references('id')->on('agreement_acceptances')->restrictOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {}
};
