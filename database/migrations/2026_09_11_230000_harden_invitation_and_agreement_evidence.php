<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('timezone', 64)->default('UTC')->after('locale');
        });

        Schema::table('groups', function (Blueprint $table): void {
            $table->string('timezone', 64)->default('UTC')->after('description');
        });

        Schema::table('group_agreement_versions', function (Blueprint $table): void {
            $table->char('content_hash', 64)->nullable()->after('content');
        });

        Schema::table('agreement_acceptances', function (Blueprint $table): void {
            $table->unsignedSmallInteger('evidence_schema_version')->default(1)->after('evidence_hash');
        });

        Schema::table('membership_agreement_acceptances', function (Blueprint $table): void {
            $table->unsignedSmallInteger('evidence_schema_version')->default(1)->after('evidence_hash');
        });

        DB::table('group_invitations')->orderBy('id')->eachById(function (object $invitation): void {
            $token = (string) $invitation->token;
            if (strlen($token) !== 64 || ! ctype_xdigit($token)) {
                DB::table('group_invitations')->where('id', $invitation->id)->update(['token' => hash('sha256', $token)]);
            }
        });

        DB::table('group_agreement_versions')->orderBy('id')->eachById(function (object $version): void {
            $canonical = preg_replace('/[ \t]+$/m', '', str_replace(["\r\n", "\r"], "\n", trim((string) $version->content))) ?? '';
            DB::table('group_agreement_versions')->where('id', $version->id)->update(['content_hash' => hash('sha256', $canonical)]);
        });

        DB::table('agreement_acceptances')->orderBy('id')->eachById(function (object $acceptance): void {
            $hash = DB::table('group_agreement_versions')->where('id', $acceptance->group_agreement_version_id)->value('content_hash');
            DB::table('agreement_acceptances')->where('id', $acceptance->id)->update(['evidence_hash' => $hash, 'evidence_schema_version' => 1]);
        });

        DB::table('membership_agreement_acceptances')->orderBy('id')->eachById(function (object $acceptance): void {
            $hash = DB::table('group_agreement_versions')->where('id', $acceptance->group_agreement_version_id)->value('content_hash');
            DB::table('membership_agreement_acceptances')->where('id', $acceptance->id)->update(['evidence_hash' => $hash, 'evidence_schema_version' => 1]);
        });
    }

    public function down(): void
    {
        Schema::table('membership_agreement_acceptances', function (Blueprint $table): void {
            $table->dropColumn('evidence_schema_version');
        });

        Schema::table('agreement_acceptances', function (Blueprint $table): void {
            $table->dropColumn('evidence_schema_version');
        });

        Schema::table('group_agreement_versions', function (Blueprint $table): void {
            $table->dropColumn('content_hash');
        });

        Schema::table('groups', function (Blueprint $table): void {
            $table->dropColumn('timezone');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('timezone');
        });
    }
};
