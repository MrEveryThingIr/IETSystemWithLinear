<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('space_content_revisions', function (Blueprint $table): void {
            $table->char('manifest_hash', 64)->nullable()->after('content_hash');
            $table->timestamp('manifest_sealed_at')->nullable()->after('manifest_hash');
            $table->index('manifest_hash', 'scr_manifest_hash_ix');
        });

        Schema::table('space_content_revision_assets', function (Blueprint $table): void {
            $table->char('asset_sha256_snapshot', 64)->nullable()->after('caption');
            $table->string('rights_status_snapshot', 32)->nullable()->after('asset_sha256_snapshot');
            $table->string('scan_status_snapshot', 32)->nullable()->after('rights_status_snapshot');
            $table->string('processing_status_snapshot', 32)->nullable()->after('scan_status_snapshot');
            $table->text('source_attribution_snapshot')->nullable()->after('processing_status_snapshot');
            $table->string('evidence_origin', 32)->nullable()->after('source_attribution_snapshot');
            $table->timestamp('published_evidence_at')->nullable()->after('evidence_origin');
        });
    }

    public function down(): void
    {
        Schema::table('space_content_revision_assets', function (Blueprint $table): void {
            $table->dropColumn([
                'asset_sha256_snapshot',
                'rights_status_snapshot',
                'scan_status_snapshot',
                'processing_status_snapshot',
                'source_attribution_snapshot',
                'evidence_origin',
                'published_evidence_at',
            ]);
        });

        Schema::table('space_content_revisions', function (Blueprint $table): void {
            $table->dropIndex('scr_manifest_hash_ix');
            $table->dropColumn(['manifest_hash', 'manifest_sealed_at']);
        });
    }
};
