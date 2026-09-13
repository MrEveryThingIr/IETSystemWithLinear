<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('space_content_definitions', function (Blueprint $table): void {
            $table->foreignId('active_version_id')->nullable()->after('current_version');
            $table->foreignId('draft_version_id')->nullable()->after('active_version_id');
            $table->index('active_version_id', 'scd_active_ver_ix');
            $table->index('draft_version_id', 'scd_draft_ver_ix');
        });

        Schema::table('space_contents', function (Blueprint $table): void {
            $table->foreignId('active_revision_id')->nullable()->after('current_revision');
            $table->foreignId('draft_revision_id')->nullable()->after('active_revision_id');
            $table->index('active_revision_id', 'sc_active_rev_ix');
            $table->index('draft_revision_id', 'sc_draft_rev_ix');
        });

        DB::table('space_content_definitions')
            ->orderBy('id')
            ->get(['id', 'status', 'current_version'])
            ->each(function (object $definition): void {
                $current = DB::table('space_content_definition_versions')
                    ->where('space_content_definition_id', $definition->id)
                    ->where('version', $definition->current_version)
                    ->first(['id', 'published_at']);

                $published = DB::table('space_content_definition_versions')
                    ->where('space_content_definition_id', $definition->id)
                    ->whereNotNull('published_at')
                    ->orderByDesc('version')
                    ->first(['id']);

                DB::table('space_content_definitions')
                    ->where('id', $definition->id)
                    ->update([
                        'active_version_id' => $published?->id,
                        'draft_version_id' => $current !== null && $current->published_at === null
                            ? $current->id
                            : null,
                    ]);
            });

        DB::table('space_contents')
            ->orderBy('id')
            ->get(['id', 'status', 'current_revision', 'published_at'])
            ->each(function (object $content): void {
                $current = DB::table('space_content_revisions')
                    ->where('space_content_id', $content->id)
                    ->where('revision', $content->current_revision)
                    ->first(['id']);

                if ($current === null) {
                    return;
                }

                $hasPublishedEdition = $content->published_at !== null;

                DB::table('space_contents')
                    ->where('id', $content->id)
                    ->update([
                        'active_revision_id' => $hasPublishedEdition ? $current->id : null,
                        'draft_revision_id' => $hasPublishedEdition ? null : $current->id,
                    ]);
            });

        Schema::table('space_content_definitions', function (Blueprint $table): void {
            $table->foreign('active_version_id', 'scd_active_ver_fk')
                ->references('id')
                ->on('space_content_definition_versions')
                ->restrictOnDelete();
            $table->foreign('draft_version_id', 'scd_draft_ver_fk')
                ->references('id')
                ->on('space_content_definition_versions')
                ->restrictOnDelete();
        });

        Schema::table('space_contents', function (Blueprint $table): void {
            $table->foreign('active_revision_id', 'sc_active_rev_fk')
                ->references('id')
                ->on('space_content_revisions')
                ->restrictOnDelete();
            $table->foreign('draft_revision_id', 'sc_draft_rev_fk')
                ->references('id')
                ->on('space_content_revisions')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        $isSqlite = DB::getDriverName() === 'sqlite';

        Schema::table('space_contents', function (Blueprint $table) use ($isSqlite): void {
            if (! $isSqlite) {
                $table->dropForeign('sc_active_rev_fk');
                $table->dropForeign('sc_draft_rev_fk');
                $table->dropIndex('sc_active_rev_ix');
                $table->dropIndex('sc_draft_rev_ix');
            }

            $table->dropColumn(['active_revision_id', 'draft_revision_id']);
        });

        Schema::table('space_content_definitions', function (Blueprint $table) use ($isSqlite): void {
            if (! $isSqlite) {
                $table->dropForeign('scd_active_ver_fk');
                $table->dropForeign('scd_draft_ver_fk');
                $table->dropIndex('scd_active_ver_ix');
                $table->dropIndex('scd_draft_ver_ix');
            }

            $table->dropColumn(['active_version_id', 'draft_version_id']);
        });
    }
};
