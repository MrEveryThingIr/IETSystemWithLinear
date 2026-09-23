<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('space_content_annotation_dispositions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('annotation_disposition_uuid_uq');
            $table->foreignId('annotation_id');
            $table->string('status', 32);
            $table->foreignId('incorporated_revision_id')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('resolved_by_actor_id');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('annotation_id', 'annotation_disposition_annotation_fk')
                ->references('id')
                ->on('space_content_annotations')
                ->restrictOnDelete();
            $table->foreign('incorporated_revision_id', 'annotation_disposition_revision_fk')
                ->references('id')
                ->on('space_content_revisions')
                ->restrictOnDelete();
            $table->foreign('resolved_by_actor_id', 'annotation_disposition_actor_fk')
                ->references('id')
                ->on('actors')
                ->restrictOnDelete();

            $table->index(['annotation_id', 'created_at'], 'annotation_disposition_history_ix');
            $table->index('incorporated_revision_id', 'annotation_disposition_revision_ix');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('space_content_annotation_dispositions');
    }
};
