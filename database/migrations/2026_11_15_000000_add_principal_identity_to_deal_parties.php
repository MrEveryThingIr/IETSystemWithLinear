<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A deal party is a principal (person, Business or Group), identified by principal_key.
 * actor_id keeps meaning "the representative/contact" and stops being the identity.
 */
return new class extends Migration
{
    private const TABLES = [
        'proposal_parties' => ['scope' => 'proposal_id', 'old' => 'proposal_parties_proposal_id_actor_id_unique'],
        'contract_version_parties' => ['scope' => 'contract_version_id', 'old' => 'contract_version_parties_version_actor_unique'],
    ];

    public function up(): void
    {
        foreach (self::TABLES as $name => $spec) {
            Schema::table($name, function (Blueprint $table): void {
                $table->foreignId('business_id')->nullable()->after('actor_id')->constrained('businesses')->restrictOnDelete();
                $table->foreignId('group_id')->nullable()->after('business_id')->constrained('groups')->restrictOnDelete();
                $table->string('principal_key', 40)->nullable()->after('group_id');
            });

            DB::table($name)->orderBy('id')->chunkById(500, function ($rows) use ($name): void {
                foreach ($rows as $row) {
                    DB::table($name)->where('id', $row->id)->update(['principal_key' => 'actor:'.$row->actor_id]);
                }
            });

            Schema::table($name, function (Blueprint $table) use ($spec, $name): void {
                $table->unique([$spec['scope'], 'principal_key'], $name.'_principal_unique');
            });

            Schema::table($name, function (Blueprint $table) use ($spec): void {
                $table->dropUnique($spec['old']);
                $table->index([$spec['scope'], 'actor_id']);
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name => $spec) {
            Schema::table($name, function (Blueprint $table) use ($spec, $name): void {
                $table->unique([$spec['scope'], 'actor_id'], $spec['old']);
                $table->dropIndex([$spec['scope'], 'actor_id']);
                $table->dropUnique($name.'_principal_unique');
                $table->dropConstrainedForeignId('business_id');
                $table->dropConstrainedForeignId('group_id');
                $table->dropColumn('principal_key');
            });
        }
    }
};
