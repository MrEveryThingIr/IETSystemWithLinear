<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('space_content_definitions', function (Blueprint $table): void {
            $table->unsignedBigInteger('context_id')->nullable()->after('id');
            $table->unsignedBigInteger('group_space_id')->nullable()->change();
        });

        Schema::table('space_contents', function (Blueprint $table): void {
            $table->unsignedBigInteger('context_id')->nullable()->after('id');
            $table->unsignedBigInteger('group_space_id')->nullable()->change();
        });

        Schema::table('space_content_render_templates', function (Blueprint $table): void {
            $table->unsignedBigInteger('context_id')->nullable()->after('id');
            $table->unsignedBigInteger('group_space_id')->nullable()->change();
        });

        Schema::table('assets', function (Blueprint $table): void {
            $table->unsignedBigInteger('context_id')->nullable()->after('uuid');
        });

        $contextBySpace = DB::table('group_space_contexts')
            ->pluck('context_id', 'group_space_id');

        foreach (['space_content_definitions', 'space_contents', 'space_content_render_templates', 'assets'] as $table) {
            DB::table($table)
                ->whereNotNull('group_space_id')
                ->orderBy('id')
                ->chunkById(100, function ($rows) use ($table, $contextBySpace): void {
                    foreach ($rows as $row) {
                        $contextId = $contextBySpace->get((int) $row->group_space_id);

                        if ($contextId === null) {
                            throw new RuntimeException('Cannot backfill '.$table.' without a GroupSpace Context.');
                        }

                        DB::table($table)
                            ->where('id', $row->id)
                            ->update(['context_id' => $contextId]);
                    }
                });
        }

        foreach (['space_content_definitions', 'space_contents', 'space_content_render_templates'] as $table) {
            if (DB::table($table)->whereNull('context_id')->exists()) {
                throw new RuntimeException('Context backfill left orphaned '.$table.' rows.');
            }
        }

        Schema::table('space_content_definitions', function (Blueprint $table): void {
            $table->unsignedBigInteger('context_id')->nullable(false)->change();
            $table->foreign('context_id', 'scd_context_fk')->references('id')->on('contexts')->restrictOnDelete();
            $table->unique(['context_id', 'slug'], 'scd_context_slug_uq');
            $table->index(['context_id', 'status'], 'scd_context_status_ix');
        });

        Schema::table('space_contents', function (Blueprint $table): void {
            $table->unsignedBigInteger('context_id')->nullable(false)->change();
            $table->foreign('context_id', 'sc_context_fk')->references('id')->on('contexts')->restrictOnDelete();
            $table->index(['context_id', 'status'], 'sc_context_status_ix');
        });

        Schema::table('space_content_render_templates', function (Blueprint $table): void {
            $table->unsignedBigInteger('context_id')->nullable(false)->change();
            $table->foreign('context_id', 'scrt_context_fk')->references('id')->on('contexts')->restrictOnDelete();
            $table->unique(['context_id', 'name'], 'scrt_context_name_uq');
            $table->index(['context_id', 'status', 'name'], 'scrt_context_status_name_ix');
        });

        Schema::table('assets', function (Blueprint $table): void {
            $table->foreign('context_id', 'asset_context_fk')->references('id')->on('contexts')->restrictOnDelete();
            $table->index(['context_id', 'created_at'], 'asset_context_created_ix');
        });
    }

    public function down(): void
    {
        $nonGroupContentExists = DB::table('space_content_definitions')->whereNull('group_space_id')->exists()
            || DB::table('space_contents')->whereNull('group_space_id')->exists()
            || DB::table('space_content_render_templates')->whereNull('group_space_id')->exists();

        $nonGroupAssetsExist = DB::table('assets')
            ->whereNotNull('context_id')
            ->whereNull('group_space_id')
            ->exists();

        if ($nonGroupContentExists || $nonGroupAssetsExist) {
            throw new RuntimeException('Cannot roll back Context-bound Content after non-Group Context data exists.');
        }

        Schema::table('assets', function (Blueprint $table): void {
            $table->dropForeign('asset_context_fk');
            $table->dropIndex('asset_context_created_ix');
            $table->dropColumn('context_id');
        });

        Schema::table('space_content_render_templates', function (Blueprint $table): void {
            $table->dropForeign('scrt_context_fk');
            $table->dropUnique('scrt_context_name_uq');
            $table->dropIndex('scrt_context_status_name_ix');
            $table->dropColumn('context_id');
            $table->unsignedBigInteger('group_space_id')->nullable(false)->change();
        });

        Schema::table('space_contents', function (Blueprint $table): void {
            $table->dropForeign('sc_context_fk');
            $table->dropIndex('sc_context_status_ix');
            $table->dropColumn('context_id');
            $table->unsignedBigInteger('group_space_id')->nullable(false)->change();
        });

        Schema::table('space_content_definitions', function (Blueprint $table): void {
            $table->dropForeign('scd_context_fk');
            $table->dropUnique('scd_context_slug_uq');
            $table->dropIndex('scd_context_status_ix');
            $table->dropColumn('context_id');
            $table->unsignedBigInteger('group_space_id')->nullable(false)->change();
        });
    }
};
