<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('space_content_annotations', function (Blueprint $table): void {
            $table->text('body')->nullable()->change();
        });

        Schema::create('space_content_annotation_anchors', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedBigInteger('annotation_id');
            $table->string('target_type', 24);
            $table->uuid('target_uuid')->nullable();
            $table->string('field_key', 96)->nullable();
            $table->json('selector')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique('uuid', 'scaa_uuid_uq');
            $table->unique(['annotation_id', 'position'], 'scaa_annotation_position_uq');
            $table->index(['target_type', 'target_uuid'], 'scaa_target_ix');
            $table->index(['annotation_id', 'target_type'], 'scaa_annotation_type_ix');
            $table->foreign('annotation_id', 'scaa_annotation_fk')->references('id')->on('space_content_annotations')->restrictOnDelete();
        });

        Schema::create('space_content_annotation_assets', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedBigInteger('annotation_id');
            $table->unsignedBigInteger('asset_id');
            $table->string('role', 24)->default('attachment');
            $table->unsignedInteger('position')->default(0);
            $table->string('caption', 1000)->nullable();
            $table->timestamps();

            $table->unique('uuid', 'scaasset_uuid_uq');
            $table->unique(['annotation_id', 'asset_id'], 'scaasset_annotation_asset_uq');
            $table->unique(['annotation_id', 'position'], 'scaasset_annotation_position_uq');
            $table->foreign('annotation_id', 'scaasset_annotation_fk')->references('id')->on('space_content_annotations')->restrictOnDelete();
            $table->foreign('asset_id', 'scaasset_asset_fk')->references('id')->on('assets')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('space_content_annotation_assets');
        Schema::dropIfExists('space_content_annotation_anchors');

        Schema::table('space_content_annotations', function (Blueprint $table): void {
            $table->text('body')->nullable(false)->change();
        });
    }
};
