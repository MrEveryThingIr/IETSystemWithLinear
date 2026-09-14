<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('space_content_revisions', function (Blueprint $table): void {
            $table->string('composition_mode', 16)->default('fields')->after('presentation');
        });

        Schema::create('space_content_blocks', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->uuid('logical_uuid');
            $table->unsignedBigInteger('space_content_revision_id');
            $table->unsignedBigInteger('parent_block_id')->nullable();
            $table->string('type', 24);
            $table->unsignedInteger('position')->default(0);
            $table->json('data');
            $table->json('style')->nullable();
            $table->timestamps();

            $table->unique('uuid', 'scb_uuid_uq');
            $table->index(['space_content_revision_id', 'parent_block_id', 'position'], 'scb_revision_parent_position_ix');
            $table->index(['logical_uuid', 'space_content_revision_id'], 'scb_logical_revision_ix');
            $table->foreign('space_content_revision_id', 'scb_revision_fk')->references('id')->on('space_content_revisions')->restrictOnDelete();
            $table->foreign('parent_block_id', 'scb_parent_fk')->references('id')->on('space_content_blocks')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('space_content_blocks');

        Schema::table('space_content_revisions', function (Blueprint $table): void {
            $table->dropColumn('composition_mode');
        });
    }
};
