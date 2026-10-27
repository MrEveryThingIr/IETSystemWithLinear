<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proposal_versions', function (Blueprint $table): void {
            // Structured Deal Terms (schema v1) and the sha256 of their canonical JSON; null on versions written before M3.
            $table->json('terms_data')->nullable()->after('terms_content_revision_id');
            $table->char('terms_hash', 64)->nullable()->after('terms_data');
        });
    }

    public function down(): void
    {
        Schema::table('proposal_versions', function (Blueprint $table): void {
            $table->dropColumn(['terms_data', 'terms_hash']);
        });
    }
};
