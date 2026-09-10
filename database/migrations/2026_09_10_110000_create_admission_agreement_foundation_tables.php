<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('candidate_actor_id')->constrained('actors')->restrictOnDelete();
            $table->foreignId('source_invitation_id')->nullable()->constrained('group_invitations')->nullOnDelete();
            $table->string('status')->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamps();
            $table->unique(['group_id', 'candidate_actor_id']);
            $table->index(['group_id', 'status']);
        });

        Schema::create('group_agreements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('required_for_admission')->default(true);
            $table->timestamps();
        });

        Schema::create('group_agreement_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('group_agreement_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->longText('content');
            $table->string('status')->default('draft');
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_until')->nullable();
            $table->foreignId('created_by_actor_id')->nullable()->constrained('actors')->nullOnDelete();
            $table->timestamps();
            $table->unique(['group_agreement_id', 'version']);
            $table->index(
                ['status', 'effective_from', 'effective_until'],
                'agreement_versions_effective_period_index',
            );
        });

        Schema::create('agreement_acceptances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('group_agreement_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('accepted_by_actor_id')->constrained('actors')->restrictOnDelete();
            $table->timestamp('accepted_at');
            $table->string('evidence_hash', 64)->nullable();
            $table->timestamps();
            $table->unique(
                ['admission_id', 'group_agreement_version_id'],
                'agreement_acceptances_admission_version_unique',
            );
        });

        Schema::create('admission_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('actors')->nullOnDelete();
            $table->string('event');
            $table->text('note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['admission_id', 'event']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_events');
        Schema::dropIfExists('agreement_acceptances');
        Schema::dropIfExists('group_agreement_versions');
        Schema::dropIfExists('group_agreements');
        Schema::dropIfExists('admissions');
    }
};
