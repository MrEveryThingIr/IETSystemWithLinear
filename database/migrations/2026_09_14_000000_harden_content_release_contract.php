<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('space_contents', function (Blueprint $table): void {
            $table->uuid('uuid')->nullable()->after('id');
            $table->unique('uuid', 'sc_uuid_uq');
        });

        Schema::table('space_content_revisions', function (Blueprint $table): void {
            $table->uuid('uuid')->nullable()->after('id');
            $table->string('evidence_status', 32)->default('unsealed')->after('content_hash');
            $table->unsignedSmallInteger('manifest_version')->nullable()->after('manifest_hash');
            $table->unsignedSmallInteger('canonicalization_version')->nullable()->after('manifest_version');
            $table->string('manifest_algorithm', 16)->nullable()->after('canonicalization_version');
            $table->longText('canonical_manifest')->nullable()->after('manifest_algorithm');
            $table->unique('uuid', 'scr_uuid_uq');
            $table->index('evidence_status', 'scr_evidence_status_ix');
        });

        Schema::table('space_content_revision_assets', function (Blueprint $table): void {
            $table->uuid('uuid')->nullable()->after('id');
            $table->unique('uuid', 'scra_uuid_uq');
        });

        Schema::table('space_content_revision_relationships', function (Blueprint $table): void {
            $table->uuid('uuid')->nullable()->after('id');
            $table->unique('uuid', 'scrr_uuid_uq');
        });

        Schema::table('assets', function (Blueprint $table): void {
            $table->text('scan_error')->nullable()->after('scan_status');
            $table->timestamp('scan_attempted_at')->nullable()->after('scan_error');
            $table->timestamp('scan_completed_at')->nullable()->after('scan_attempted_at');
            $table->text('processing_error')->nullable()->after('processing_status');
            $table->timestamp('processing_completed_at')->nullable()->after('processing_error');
            $table->timestamp('readiness_verified_at')->nullable()->after('processing_completed_at');
            $table->index(['scan_status', 'processing_status'], 'asset_readiness_ix');
        });

        Schema::create('space_content_lifecycle_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique('scle_uuid_uq');
            $table->foreignId('space_content_id');
            $table->foreignId('actor_id');
            $table->string('event_type', 32);
            $table->string('from_status', 32);
            $table->string('to_status', 32);
            $table->string('reason', 1000);
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');

            $table->foreign('space_content_id', 'scle_content_fk')
                ->references('id')->on('space_contents')->restrictOnDelete();
            $table->foreign('actor_id', 'scle_actor_fk')
                ->references('id')->on('actors')->restrictOnDelete();
            $table->index(['space_content_id', 'created_at'], 'scle_content_created_ix');
        });

        $this->backfillUuid('space_contents');
        $this->backfillUuid('space_content_revisions');
        $this->backfillUuid('space_content_revision_assets');
        $this->backfillUuid('space_content_revision_relationships');

        Schema::table('space_contents', fn (Blueprint $table) => $table->uuid('uuid')->nullable(false)->change());
        Schema::table('space_content_revisions', fn (Blueprint $table) => $table->uuid('uuid')->nullable(false)->change());
        Schema::table('space_content_revision_assets', fn (Blueprint $table) => $table->uuid('uuid')->nullable(false)->change());
        Schema::table('space_content_revision_relationships', fn (Blueprint $table) => $table->uuid('uuid')->nullable(false)->change());

        DB::table('space_content_revisions')
            ->whereNotNull('manifest_hash')
            ->update([
                'evidence_status' => 'legacy_sealed_v0',
                'manifest_version' => 0,
                'canonicalization_version' => 1,
                'manifest_algorithm' => 'sha256',
            ]);

        $activeRevisionIds = DB::table('space_contents')
            ->whereNotNull('active_revision_id')
            ->pluck('active_revision_id')
            ->all();

        if ($activeRevisionIds !== []) {
            DB::table('space_content_revisions')
                ->whereIn('id', $activeRevisionIds)
                ->whereNull('manifest_hash')
                ->update(['evidence_status' => 'legacy_unsealed']);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('space_content_lifecycle_events');

        Schema::table('assets', function (Blueprint $table): void {
            $table->dropIndex('asset_readiness_ix');
            $table->dropColumn([
                'scan_error',
                'scan_attempted_at',
                'scan_completed_at',
                'processing_error',
                'processing_completed_at',
                'readiness_verified_at',
            ]);
        });

        Schema::table('space_content_revision_relationships', function (Blueprint $table): void {
            $table->dropUnique('scrr_uuid_uq');
            $table->dropColumn('uuid');
        });

        Schema::table('space_content_revision_assets', function (Blueprint $table): void {
            $table->dropUnique('scra_uuid_uq');
            $table->dropColumn('uuid');
        });

        Schema::table('space_content_revisions', function (Blueprint $table): void {
            $table->dropIndex('scr_evidence_status_ix');
            $table->dropUnique('scr_uuid_uq');
            $table->dropColumn([
                'uuid',
                'evidence_status',
                'manifest_version',
                'canonicalization_version',
                'manifest_algorithm',
                'canonical_manifest',
            ]);
        });

        Schema::table('space_contents', function (Blueprint $table): void {
            $table->dropUnique('sc_uuid_uq');
            $table->dropColumn('uuid');
        });
    }

    private function backfillUuid(string $table): void
    {
        DB::table($table)
            ->whereNull('uuid')
            ->orderBy('id')
            ->get(['id'])
            ->each(function (object $row) use ($table): void {
                DB::table($table)
                    ->where('id', $row->id)
                    ->update(['uuid' => (string) Str::uuid()]);
            });
    }
};
