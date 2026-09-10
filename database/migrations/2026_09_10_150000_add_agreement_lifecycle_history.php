<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('group_agreement_versions', function (Blueprint $table): void {
            $table->dropForeign(['group_agreement_id']);
            $table->foreign('group_agreement_id')->references('id')->on('group_agreements')->restrictOnDelete();
            $table->text('rationale')->nullable()->after('content');
            $table->text('decision_note')->nullable()->after('rationale');
            $table->boolean('reacceptance_required')->default(true)->after('status');
            $table->foreignId('approved_by_actor_id')->nullable()->after('created_by_actor_id')->constrained('actors')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->foreignId('superseded_by_version_id')->nullable()->constrained('group_agreement_versions')->nullOnDelete();
            $table->index(['group_agreement_id', 'status']);
        });

        Schema::table('agreement_acceptances', function (Blueprint $table): void {
            $table->dropForeign(['group_agreement_version_id']);
            $table->foreign('group_agreement_version_id')->references('id')->on('group_agreement_versions')->restrictOnDelete();
        });

        Schema::create('agreement_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('group_agreement_id')->constrained()->restrictOnDelete();
            $table->foreignId('group_agreement_version_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('actors')->nullOnDelete();
            $table->string('event');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['group_agreement_version_id', 'event']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agreement_events');

        Schema::table('agreement_acceptances', function (Blueprint $table): void {
            $table->dropForeign(['group_agreement_version_id']);
            $table->foreign('group_agreement_version_id')->references('id')->on('group_agreement_versions')->cascadeOnDelete();
        });

        Schema::table('group_agreement_versions', function (Blueprint $table): void {
            $table->dropForeign(['approved_by_actor_id']);
            $table->dropForeign(['superseded_by_version_id']);
            $table->dropIndex(['group_agreement_id', 'status']);
            $table->dropColumn([
                'rationale',
                'decision_note',
                'reacceptance_required',
                'approved_by_actor_id',
                'approved_at',
                'published_at',
                'activated_at',
                'superseded_by_version_id',
            ]);
            $table->dropForeign(['group_agreement_id']);
            $table->foreign('group_agreement_id')->references('id')->on('group_agreements')->cascadeOnDelete();
        });
    }
};
