<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('space_contents', function (Blueprint $table): void {
            $table->json('interaction_settings')->nullable()->after('content_blueprint_version_id');
        });

        DB::table('space_contents')
            ->whereNotNull('content_blueprint_version_id')
            ->orderBy('id')
            ->chunkById(100, function ($contents): void {
                foreach ($contents as $content) {
                    $raw = DB::table('content_blueprint_versions')
                        ->where('id', $content->content_blueprint_version_id)
                        ->value('interaction_defaults');

                    $decoded = is_array($raw)
                        ? $raw
                        : (is_string($raw) ? json_decode($raw, true) : null);
                    $decoded = is_array($decoded) ? $decoded : [];

                    $visibility = $decoded['default_annotation_visibility'] ?? 'private';
                    if (! is_string($visibility) || ! in_array($visibility, ['private', 'space'], true)) {
                        $visibility = 'private';
                    }

                    DB::table('space_contents')
                        ->where('id', $content->id)
                        ->update([
                            'interaction_settings' => json_encode([
                                'annotations' => (bool) ($decoded['annotations'] ?? true),
                                'reactions' => (bool) ($decoded['reactions'] ?? true),
                                'default_annotation_visibility' => $visibility,
                            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('space_contents', function (Blueprint $table): void {
            $table->dropColumn('interaction_settings');
        });
    }
};
