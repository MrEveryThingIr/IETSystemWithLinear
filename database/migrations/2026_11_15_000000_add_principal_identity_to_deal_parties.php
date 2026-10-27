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
    public function up(): void
    {
        Schema::table('proposal_parties', function (Blueprint $table): void {
            $table->foreignId('business_id')->nullable()->after('actor_id')->constrained('businesses')->restrictOnDelete();
            $table->foreignId('group_id')->nullable()->after('business_id')->constrained('groups')->restrictOnDelete();
            $table->string('principal_key', 40)->nullable()->after('group_id');
        });
        $this->backfill('proposal_parties');
        Schema::table('proposal_parties', function (Blueprint $table): void {
            $table->unique(['proposal_id', 'principal_key'], 'proposal_parties_principal_unique');
        });
        Schema::table('proposal_parties', function (Blueprint $table): void {
            $table->dropUnique('proposal_parties_proposal_id_actor_id_unique');
            $table->index(['proposal_id', 'actor_id']);
        });

        Schema::table('contract_version_parties', function (Blueprint $table): void {
            $table->foreignId('business_id')->nullable()->after('actor_id')->constrained('businesses')->restrictOnDelete();
            $table->foreignId('group_id')->nullable()->after('business_id')->constrained('groups')->restrictOnDelete();
            $table->string('principal_key', 40)->nullable()->after('group_id');
        });
        $this->backfill('contract_version_parties');
        Schema::table('contract_version_parties', function (Blueprint $table): void {
            $table->unique(['contract_version_id', 'principal_key'], 'contract_version_parties_principal_unique');
        });
        Schema::table('contract_version_parties', function (Blueprint $table): void {
            $table->dropUnique('contract_version_parties_version_actor_unique');
            $table->index(['contract_version_id', 'actor_id']);
        });
    }

    public function down(): void
    {
        Schema::table('proposal_parties', function (Blueprint $table): void {
            $table->unique(['proposal_id', 'actor_id'], 'proposal_parties_proposal_id_actor_id_unique');
            $table->dropIndex(['proposal_id', 'actor_id']);
            $table->dropUnique('proposal_parties_principal_unique');
            $table->dropConstrainedForeignId('business_id');
            $table->dropConstrainedForeignId('group_id');
            $table->dropColumn('principal_key');
        });

        Schema::table('contract_version_parties', function (Blueprint $table): void {
            $table->unique(['contract_version_id', 'actor_id'], 'contract_version_parties_version_actor_unique');
            $table->dropIndex(['contract_version_id', 'actor_id']);
            $table->dropUnique('contract_version_parties_principal_unique');
            $table->dropConstrainedForeignId('business_id');
            $table->dropConstrainedForeignId('group_id');
            $table->dropColumn('principal_key');
        });
    }

    /** Legacy rows are all person parties: their identity is their own Actor. */
    private function backfill(string $table): void
    {
        DB::table($table)->orderBy('id')->chunkById(500, function ($rows) use ($table): void {
            foreach ($rows as $row) {
                DB::table($table)->where('id', $row->id)->update(['principal_key' => 'actor:'.$row->actor_id]);
            }
        });
    }
};
