<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_blueprints', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('slug', 120)->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('category', 64)->default('general');
            $table->string('scope', 16)->default('system');
            $table->foreignId('owner_actor_id')->nullable();
            $table->foreignId('context_id')->nullable();
            $table->string('status', 16)->default('active');
            $table->unsignedInteger('current_version')->default(1);
            $table->unsignedBigInteger('active_version_id')->nullable();
            $table->unsignedBigInteger('draft_version_id')->nullable();
            $table->unsignedBigInteger('cloned_from_version_id')->nullable();
            $table->timestamps();

            $table->foreign('owner_actor_id', 'cb_owner_fk')->references('id')->on('actors')->restrictOnDelete();
            $table->foreign('context_id', 'cb_context_fk')->references('id')->on('contexts')->restrictOnDelete();
            $table->index(['scope', 'status', 'category'], 'cb_scope_status_category_ix');
            $table->index(['owner_actor_id', 'status'], 'cb_owner_status_ix');
            $table->index(['context_id', 'status'], 'cb_context_status_ix');
        });

        Schema::create('content_blueprint_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('content_blueprint_id');
            $table->unsignedInteger('version');
            $table->json('definition_schema');
            $table->json('initial_blocks')->nullable();
            $table->string('render_template_key', 32)->default('article');
            $table->json('presentation')->nullable();
            $table->json('context_kinds');
            $table->json('concept_defaults')->nullable();
            $table->json('interaction_defaults')->nullable();
            $table->json('authoring')->nullable();
            $table->foreignId('created_by_actor_id')->nullable();
            $table->char('content_hash', 64);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->foreign('content_blueprint_id', 'cbv_blueprint_fk')->references('id')->on('content_blueprints')->restrictOnDelete();
            $table->foreign('created_by_actor_id', 'cbv_creator_fk')->references('id')->on('actors')->restrictOnDelete();
            $table->unique(['content_blueprint_id', 'version'], 'cbv_blueprint_version_uq');
            $table->index(['content_blueprint_id', 'published_at'], 'cbv_blueprint_published_ix');
        });

        Schema::table('content_blueprints', function (Blueprint $table): void {
            $table->foreign('active_version_id', 'cb_active_version_fk')->references('id')->on('content_blueprint_versions')->restrictOnDelete();
            $table->foreign('draft_version_id', 'cb_draft_version_fk')->references('id')->on('content_blueprint_versions')->restrictOnDelete();
            $table->foreign('cloned_from_version_id', 'cb_clone_version_fk')->references('id')->on('content_blueprint_versions')->restrictOnDelete();
        });

        Schema::table('space_content_definitions', function (Blueprint $table): void {
            $table->foreignId('content_blueprint_version_id')->nullable()->after('context_id');
            $table->foreign('content_blueprint_version_id', 'scd_blueprint_version_fk')
                ->references('id')
                ->on('content_blueprint_versions')
                ->restrictOnDelete();
            $table->unique(['context_id', 'content_blueprint_version_id'], 'scd_context_blueprint_version_uq');
        });

        Schema::table('space_contents', function (Blueprint $table): void {
            $table->foreignId('content_blueprint_version_id')->nullable()->after('context_id');
            $table->foreign('content_blueprint_version_id', 'sc_blueprint_version_fk')
                ->references('id')
                ->on('content_blueprint_versions')
                ->restrictOnDelete();
            $table->index(['content_blueprint_version_id', 'status'], 'sc_blueprint_status_ix');
        });
    }

    public function down(): void
    {
        Schema::table('space_contents', function (Blueprint $table): void {
            $table->dropForeign(DB::connection()->getDriverName() === 'sqlite' ? ['content_blueprint_version_id'] : 'sc_blueprint_version_fk');
            $table->dropIndex('sc_blueprint_status_ix');
            $table->dropColumn('content_blueprint_version_id');
        });

        Schema::table('space_content_definitions', function (Blueprint $table): void {
            $table->dropForeign(DB::connection()->getDriverName() === 'sqlite' ? ['content_blueprint_version_id'] : 'scd_blueprint_version_fk');
            $table->dropUnique('scd_context_blueprint_version_uq');
            $table->dropColumn('content_blueprint_version_id');
        });

        Schema::table('content_blueprints', function (Blueprint $table): void {
            $table->dropForeign(DB::connection()->getDriverName() === 'sqlite' ? ['active_version_id'] : 'cb_active_version_fk');
            $table->dropForeign(DB::connection()->getDriverName() === 'sqlite' ? ['draft_version_id'] : 'cb_draft_version_fk');
            $table->dropForeign(DB::connection()->getDriverName() === 'sqlite' ? ['cloned_from_version_id'] : 'cb_clone_version_fk');
        });

        Schema::dropIfExists('content_blueprint_versions');
        Schema::dropIfExists('content_blueprints');
    }
};
