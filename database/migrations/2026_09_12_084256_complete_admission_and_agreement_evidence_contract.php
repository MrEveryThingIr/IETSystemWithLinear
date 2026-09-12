<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('admissions', 'open_key')) {
            Schema::table('admissions', function (Blueprint $table): void {
                $table->string('open_key')->nullable()->after('status')->unique();
            });
        }

        DB::table('admissions')
            ->whereNotIn('status', ['finalized', 'rejected', 'cancelled'])
            ->orderBy('id')
            ->each(function (object $admission): void {
                DB::table('admissions')->where('id', $admission->id)->update([
                    'open_key' => $admission->group_id.':'.$admission->candidate_actor_id,
                ]);
            });

        if (Schema::hasIndex('admissions', 'admissions_group_id_candidate_actor_id_unique')) {
            Schema::table('admissions', function (Blueprint $table): void {
                $table->dropUnique(['group_id', 'candidate_actor_id']);
            });
        }

        if (! Schema::hasIndex('admissions', 'admissions_group_id_candidate_actor_id_created_at_index')) {
            Schema::table('admissions', function (Blueprint $table): void {
                $table->index(['group_id', 'candidate_actor_id', 'created_at']);
            });
        }

        foreach (['agreement_acceptances', 'membership_agreement_acceptances'] as $tableName) {
            if (! Schema::hasColumn($tableName, 'group_agreement_id')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->foreignId('group_agreement_id')->nullable()->after('group_agreement_version_id')->constrained()->restrictOnDelete();
                    $table->unsignedInteger('version_number')->nullable()->after('group_agreement_id');
                    $table->string('hash_algorithm', 20)->default('sha256')->after('evidence_hash');
                    $table->timestamp('version_effective_from')->nullable()->after('hash_algorithm');
                    $table->timestamp('version_effective_until')->nullable()->after('version_effective_from');
                    $table->boolean('required_for_admission')->nullable()->after('version_effective_until');
                    $table->boolean('reacceptance_required')->nullable()->after('required_for_admission');
                    $table->foreignId('represented_actor_id')->nullable()->after('accepted_by_actor_id')->constrained('actors')->restrictOnDelete();
                    $table->foreignId('acting_user_id')->nullable()->after('represented_actor_id')->constrained('users')->restrictOnDelete();
                });
            }
        }

        if (! Schema::hasIndex('membership_agreement_acceptances', 'membership_agreement_acceptances_group_membership_id_index')) {
            Schema::table('membership_agreement_acceptances', function (Blueprint $table): void {
                $table->index('group_membership_id');
            });
        }

        if (Schema::hasIndex('membership_agreement_acceptances', 'membership_acceptances_membership_version_unique')) {
            Schema::table('membership_agreement_acceptances', function (Blueprint $table): void {
                $table->dropUnique('membership_acceptances_membership_version_unique');
            });
        }

        if (! Schema::hasColumn('membership_agreement_acceptances', 'source_admission_acceptance_id')) {
            Schema::table('membership_agreement_acceptances', function (Blueprint $table): void {
                $table->foreignId('source_admission_acceptance_id')->nullable()->after('id');
            });
        }

        if (! Schema::hasForeignKey('membership_agreement_acceptances', 'membership_acceptance_source_foreign')) {
            Schema::table('membership_agreement_acceptances', function (Blueprint $table): void {
                $table->foreign('source_admission_acceptance_id', 'membership_acceptance_source_foreign')
                    ->references('id')->on('agreement_acceptances')->restrictOnDelete();
            });
        }

        if (! Schema::hasColumn('membership_agreement_acceptances', 'group_membership_event_id')) {
            Schema::table('membership_agreement_acceptances', function (Blueprint $table): void {
                $table->foreignId('group_membership_event_id')->nullable()->after('group_membership_id');
            });
        }

        if (! Schema::hasForeignKey('membership_agreement_acceptances', 'membership_acceptance_event_foreign')) {
            Schema::table('membership_agreement_acceptances', function (Blueprint $table): void {
                $table->foreign('group_membership_event_id', 'membership_acceptance_event_foreign')
                    ->references('id')->on('group_membership_events')->restrictOnDelete();
            });
        }

        $this->backfillMembershipEvents();
        $this->backfillAcceptanceEvidence('agreement_acceptances');
        $this->backfillAcceptanceEvidence('membership_agreement_acceptances');

        Schema::table('membership_agreement_acceptances', function (Blueprint $table): void {
            $table->unsignedBigInteger('group_membership_event_id')->nullable(false)->change();
        });

        foreach (['agreement_acceptances', 'membership_agreement_acceptances'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->unsignedBigInteger('group_agreement_id')->nullable(false)->change();
                $table->unsignedInteger('version_number')->nullable(false)->change();
                $table->string('evidence_hash', 64)->nullable(false)->change();
                $table->boolean('required_for_admission')->nullable(false)->change();
                $table->boolean('reacceptance_required')->nullable(false)->change();
                $table->unsignedBigInteger('represented_actor_id')->nullable(false)->change();
            });
        }

        if (! Schema::hasIndex('membership_agreement_acceptances', 'membership_acceptance_source_unique')) {
            Schema::table('membership_agreement_acceptances', function (Blueprint $table): void {
                $table->unique('source_admission_acceptance_id', 'membership_acceptance_source_unique');
            });
        }

        if (! Schema::hasIndex('membership_agreement_acceptances', 'membership_acceptance_period_version_unique')) {
            Schema::table('membership_agreement_acceptances', function (Blueprint $table): void {
                $table->unique(['group_membership_event_id', 'group_agreement_version_id'], 'membership_acceptance_period_version_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $hasRepeatedAdmissions = DB::table('admissions')
            ->select(['group_id', 'candidate_actor_id'])
            ->groupBy(['group_id', 'candidate_actor_id'])
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasRepeatedAdmissions) {
            throw new RuntimeException('Cannot restore the legacy one-admission-per-actor constraint while repeated admission history exists.');
        }

        $hasRepeatedMembershipAcceptances = DB::table('membership_agreement_acceptances')
            ->select(['group_membership_id', 'group_agreement_version_id'])
            ->groupBy(['group_membership_id', 'group_agreement_version_id'])
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasRepeatedMembershipAcceptances) {
            throw new RuntimeException('Cannot restore the legacy one-acceptance-per-membership-version constraint while repeated acceptance history exists.');
        }

        Schema::table('membership_agreement_acceptances', function (Blueprint $table): void {
            $table->dropUnique('membership_acceptance_period_version_unique');
            $table->dropUnique('membership_acceptance_source_unique');
        });

        Schema::table('membership_agreement_acceptances', function (Blueprint $table): void {
            if (DB::connection()->getDriverName() === 'sqlite') {
                $table->dropForeign(['source_admission_acceptance_id']);
                $table->dropForeign(['group_membership_event_id']);
            } else {
                $table->dropForeign('membership_acceptance_source_foreign');
                $table->dropForeign('membership_acceptance_event_foreign');
            }
        });

        Schema::table('membership_agreement_acceptances', function (Blueprint $table): void {
            $table->dropColumn('source_admission_acceptance_id');
            $table->dropColumn('group_membership_event_id');
            $table->unique(['group_membership_id', 'group_agreement_version_id'], 'membership_acceptances_membership_version_unique');
        });

        if (Schema::hasIndex('membership_agreement_acceptances', 'membership_agreement_acceptances_group_membership_id_index')) {
            Schema::table('membership_agreement_acceptances', function (Blueprint $table): void {
                $table->dropIndex(['group_membership_id']);
            });
        }

        DB::table('group_membership_events')->whereIn('event', ['membership.migrated_initial', 'membership.migrated_status'])->delete();

        foreach (['membership_agreement_acceptances', 'agreement_acceptances'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropForeign(['group_agreement_id']);
                $table->dropForeign(['represented_actor_id']);
                $table->dropForeign(['acting_user_id']);
                $table->dropColumn([
                    'group_agreement_id',
                    'version_number',
                    'hash_algorithm',
                    'version_effective_from',
                    'version_effective_until',
                    'required_for_admission',
                    'reacceptance_required',
                    'represented_actor_id',
                    'acting_user_id',
                ]);
                $table->string('evidence_hash', 64)->nullable()->change();
            });
        }

        Schema::table('admissions', function (Blueprint $table): void {
            $table->dropIndex(['group_id', 'candidate_actor_id', 'created_at']);
            $table->dropUnique(['open_key']);
            $table->dropColumn('open_key');
            $table->unique(['group_id', 'candidate_actor_id']);
        });
    }

    private function backfillAcceptanceEvidence(string $tableName): void
    {
        DB::table($tableName)->orderBy('id')->each(function (object $acceptance) use ($tableName): void {
            $version = DB::table('group_agreement_versions')->where('id', $acceptance->group_agreement_version_id)->first();

            if ($version === null) {
                throw new RuntimeException("Cannot backfill {$tableName} without its agreement version.");
            }

            $agreement = DB::table('group_agreements')->where('id', $version->group_agreement_id)->first();
            $actor = DB::table('actors')->where('id', $acceptance->accepted_by_actor_id)->first();

            if ($agreement === null || $actor === null) {
                throw new RuntimeException("Cannot backfill {$tableName} without its agreement or accepting Actor.");
            }

            DB::table($tableName)->where('id', $acceptance->id)->update([
                'group_agreement_id' => $agreement->id,
                'version_number' => $version->version,
                'evidence_hash' => $version->content_hash,
                'hash_algorithm' => 'sha256',
                'version_effective_from' => $version->effective_from,
                'version_effective_until' => $version->effective_until,
                'required_for_admission' => $agreement->required_for_admission,
                'reacceptance_required' => $version->reacceptance_required,
                'represented_actor_id' => $acceptance->accepted_by_actor_id,
                'acting_user_id' => $actor->user_id,
                ...($tableName === 'membership_agreement_acceptances' ? [
                    'group_membership_event_id' => $this->membershipEventIdForAcceptance($acceptance),
                ] : []),
            ]);
        });
    }

    private function backfillMembershipEvents(): void
    {
        DB::table('group_memberships')->orderBy('id')->each(function (object $membership): void {
            if (DB::table('group_membership_events')->where('group_membership_id', $membership->id)->exists()) {
                return;
            }

            DB::table('group_membership_events')->insert([
                'group_membership_id' => $membership->id,
                'group_id' => $membership->group_id,
                'acting_actor_id' => null,
                'event' => 'membership.migrated_initial',
                'from_status' => null,
                'to_status' => 'active',
                'reason' => 'Initial lifecycle state reconstructed during workflow-integrity migration.',
                'metadata' => json_encode(['migration' => '2026_09_12_084256']),
                'created_at' => $membership->created_at,
            ]);

            if ($membership->status !== 'active') {
                DB::table('group_membership_events')->insert([
                    'group_membership_id' => $membership->id,
                    'group_id' => $membership->group_id,
                    'acting_actor_id' => null,
                    'event' => 'membership.migrated_status',
                    'from_status' => 'active',
                    'to_status' => $membership->status,
                    'reason' => 'Current lifecycle state reconstructed during workflow-integrity migration.',
                    'metadata' => json_encode(['migration' => '2026_09_12_084256']),
                    'created_at' => $membership->updated_at,
                ]);
            }
        });
    }

    private function membershipEventIdForAcceptance(object $acceptance): int
    {
        $eventId = DB::table('group_membership_events')
            ->where('group_membership_id', $acceptance->group_membership_id)
            ->where('to_status', 'active')
            ->where('created_at', '<=', $acceptance->accepted_at)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->value('id');

        if ($eventId === null) {
            $membership = DB::table('group_memberships')->where('id', $acceptance->group_membership_id)->first();

            if ($membership === null) {
                throw new RuntimeException('Cannot bind membership acceptance evidence without its Membership.');
            }

            $eventId = DB::table('group_membership_events')->insertGetId([
                'group_membership_id' => $membership->id,
                'group_id' => $membership->group_id,
                'acting_actor_id' => null,
                'event' => 'membership.migrated_initial',
                'from_status' => null,
                'to_status' => 'active',
                'reason' => 'Historical participation period reconstructed during workflow-integrity migration.',
                'metadata' => json_encode(['migration' => '2026_09_12_084256']),
                'created_at' => min($membership->created_at, $acceptance->accepted_at),
            ]);
        }

        return (int) $eventId;
    }
};
