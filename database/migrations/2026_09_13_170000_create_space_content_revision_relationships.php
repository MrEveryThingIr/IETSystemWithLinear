<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('space_content_revision_relationships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_revision_id');
            $table->foreignId('child_content_id');
            $table->string('relation_type', 32)->default('contains');
            $table->unsignedSmallInteger('position')->default(0);
            $table->foreignId('child_revision_id')->nullable();
            $table->char('child_manifest_hash', 64)->nullable();
            $table->timestamp('sealed_at')->nullable();
            $table->timestamps();

            $table->foreign('parent_revision_id', 'scrr_parent_rev_fk')
                ->references('id')->on('space_content_revisions')->restrictOnDelete();
            $table->foreign('child_content_id', 'scrr_child_content_fk')
                ->references('id')->on('space_contents')->restrictOnDelete();
            $table->foreign('child_revision_id', 'scrr_child_rev_fk')
                ->references('id')->on('space_content_revisions')->restrictOnDelete();

            $table->unique(
                ['parent_revision_id', 'relation_type', 'child_content_id'],
                'scrr_parent_type_child_uq',
            );
            $table->unique(
                ['parent_revision_id', 'relation_type', 'position'],
                'scrr_parent_type_pos_uq',
            );
            $table->index(['child_content_id', 'relation_type'], 'scrr_child_type_ix');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('space_content_revision_relationships');
    }
};
