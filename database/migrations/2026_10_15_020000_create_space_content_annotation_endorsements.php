<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A teacher (or any role the content author allows) marks an answer as
        // correct. One endorsement per person per annotation.
        Schema::create('space_content_annotation_endorsements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('annotation_id');
            $table->foreignId('actor_id');
            $table->dateTime('created_at');

            $table->foreign('annotation_id', 'scae_annotation_fk')
                ->references('id')->on('space_content_annotations')->restrictOnDelete();
            $table->foreign('actor_id', 'scae_actor_fk')
                ->references('id')->on('actors')->restrictOnDelete();
            $table->unique(['annotation_id', 'actor_id'], 'scae_annotation_actor_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('space_content_annotation_endorsements');
    }
};
