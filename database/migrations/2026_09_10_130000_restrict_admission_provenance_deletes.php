<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admissions', function (Blueprint $table): void {
            $table->dropForeign(['source_invitation_id']);
            $table->foreign('source_invitation_id')->references('id')->on('group_invitations')->restrictOnDelete();
        });
        Schema::table('agreement_acceptances', function (Blueprint $table): void {
            $table->dropForeign(['group_agreement_version_id']);
            $table->foreign('group_agreement_version_id')->references('id')->on('group_agreement_versions')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('agreement_acceptances', function (Blueprint $table): void {
            $table->dropForeign(['group_agreement_version_id']);
            $table->foreign('group_agreement_version_id')->references('id')->on('group_agreement_versions')->cascadeOnDelete();
        });
        Schema::table('admissions', function (Blueprint $table): void {
            $table->dropForeign(['source_invitation_id']);
            $table->foreign('source_invitation_id')->references('id')->on('group_invitations')->nullOnDelete();
        });
    }
};
