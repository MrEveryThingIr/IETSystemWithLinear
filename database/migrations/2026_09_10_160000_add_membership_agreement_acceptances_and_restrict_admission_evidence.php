<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agreement_acceptances', function (Blueprint $table): void {
            $table->dropForeign(['admission_id']);
            $table->foreign('admission_id')->references('id')->on('admissions')->restrictOnDelete();
        });

        Schema::table('admission_events', function (Blueprint $table): void {
            $table->dropForeign(['admission_id']);
            $table->foreign('admission_id')->references('id')->on('admissions')->restrictOnDelete();
        });

        Schema::create('membership_agreement_acceptances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('group_membership_id')->constrained()->restrictOnDelete();
            $table->foreignId('group_agreement_version_id')->constrained()->restrictOnDelete();
            $table->foreignId('accepted_by_actor_id')->constrained('actors')->restrictOnDelete();
            $table->timestamp('accepted_at');
            $table->string('evidence_hash', 64)->nullable();
            $table->timestamps();
            $table->unique(['group_membership_id', 'group_agreement_version_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_agreement_acceptances');

        Schema::table('admission_events', function (Blueprint $table): void {
            $table->dropForeign(['admission_id']);
            $table->foreign('admission_id')->references('id')->on('admissions')->cascadeOnDelete();
        });

        Schema::table('agreement_acceptances', function (Blueprint $table): void {
            $table->dropForeign(['admission_id']);
            $table->foreign('admission_id')->references('id')->on('admissions')->cascadeOnDelete();
        });
    }
};
