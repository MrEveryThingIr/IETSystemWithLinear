<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('space_content_render_templates', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedBigInteger('group_space_id');
            $table->unsignedBigInteger('creator_actor_id');
            $table->string('name', 120);
            $table->string('base_key', 32);
            $table->json('tokens');
            $table->string('status', 16)->default('active');
            $table->timestamps();

            $table->unique('uuid', 'scrt_uuid_uq');
            $table->unique(['group_space_id', 'name'], 'scrt_space_name_uq');
            $table->index(['group_space_id', 'status', 'name'], 'scrt_space_status_name_ix');
            $table->foreign('group_space_id', 'scrt_space_fk')->references('id')->on('group_spaces')->restrictOnDelete();
            $table->foreign('creator_actor_id', 'scrt_creator_fk')->references('id')->on('actors')->restrictOnDelete();
        });

        Schema::create('space_content_render_template_favorites', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('render_template_id');
            $table->unsignedBigInteger('actor_id');
            $table->timestamps();

            $table->unique(['render_template_id', 'actor_id'], 'scrtf_template_actor_uq');
            $table->foreign('render_template_id', 'scrtf_template_fk')->references('id')->on('space_content_render_templates')->cascadeOnDelete();
            $table->foreign('actor_id', 'scrtf_actor_fk')->references('id')->on('actors')->restrictOnDelete();
        });

        Schema::table('space_content_revisions', function (Blueprint $table): void {
            $table->string('render_template_key', 32)->default('article')->after('payload');
            $table->uuid('render_template_uuid')->nullable()->after('render_template_key');
            $table->json('presentation')->nullable()->after('render_template_uuid');
        });
    }

    public function down(): void
    {
        Schema::table('space_content_revisions', function (Blueprint $table): void {
            $table->dropColumn(['render_template_key', 'render_template_uuid', 'presentation']);
        });

        Schema::dropIfExists('space_content_render_template_favorites');
        Schema::dropIfExists('space_content_render_templates');
    }
};
