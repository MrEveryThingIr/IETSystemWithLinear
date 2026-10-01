<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const USER_TYPE = 'App\\Models\\User';

    private const ACTOR_TYPE = 'App\\Models\\Actor';

    public function up(): void
    {
        $this->movePolymorphicRows('contact_points', 'contactable_type', 'contactable_id', [
            'kind',
            'normalized_value',
        ]);
        $this->movePolymorphicRows('actor_addresses', 'addressable_type', 'addressable_id');
        $this->alignActorProfessions();
        $this->alignBusinesses();
        $this->alignBusinessMemberships();
    }

    public function down(): void
    {
        // Irreversible by design: domain ownership must remain Actor-scoped.
    }

    private function movePolymorphicRows(
        string $table,
        string $typeColumn,
        string $idColumn,
        array $uniqueColumns = []
    ): void {
        if (! Schema::hasTable($table)) {
            return;
        }

        DB::table($table)
            ->whereIn($typeColumn, [self::USER_TYPE, 'user'])
            ->orderBy('id')
            ->get()
            ->each(function (object $row) use ($table, $typeColumn, $idColumn, $uniqueColumns): void {
                $actorId = DB::table('actors')->where('user_id', $row->{$idColumn})->value('id');

                if (! $actorId) {
                    throw new RuntimeException(
                        "Cannot migrate {$table} row {$row->id}: User {$row->{$idColumn}} has no Actor."
                    );
                }

                if ($uniqueColumns !== []) {
                    $duplicate = DB::table($table)
                        ->where($typeColumn, self::ACTOR_TYPE)
                        ->where($idColumn, $actorId)
                        ->where(function ($query) use ($row, $uniqueColumns): void {
                            foreach ($uniqueColumns as $column) {
                                $query->where($column, $row->{$column});
                            }
                        })
                        ->exists();

                    if ($duplicate) {
                        throw new RuntimeException(
                            "Cannot migrate {$table} row {$row->id}: an equivalent Actor-owned row already exists."
                        );
                    }
                }

                DB::table($table)->where('id', $row->id)->update([
                    $typeColumn => self::ACTOR_TYPE,
                    $idColumn => $actorId,
                    'updated_at' => now(),
                ]);
            });
    }

    private function alignActorProfessions(): void
    {
        if (
            ! Schema::hasTable('actor_professions')
            || ! Schema::hasColumn('actor_professions', 'actorable_id')
        ) {
            return;
        }

        Schema::table('actor_professions', function (Blueprint $table): void {
            $table->unsignedBigInteger('actor_id')->nullable()->after('id');
        });

        DB::table('actor_professions')->orderBy('id')->get()->each(function (object $row): void {
            if (! in_array($row->actorable_type, [self::USER_TYPE, 'user'], true)) {
                throw new RuntimeException(
                    "Cannot migrate actor_professions row {$row->id}: unsupported owner type {$row->actorable_type}."
                );
            }

            $actorId = DB::table('actors')->where('user_id', $row->actorable_id)->value('id');

            if (! $actorId) {
                throw new RuntimeException(
                    "Cannot migrate actor_professions row {$row->id}: User {$row->actorable_id} has no Actor."
                );
            }

            $duplicate = DB::table('actor_professions')
                ->where('actor_id', $actorId)
                ->where('profession_id', $row->profession_id)
                ->where('id', '!=', $row->id)
                ->exists();

            if ($duplicate) {
                throw new RuntimeException(
                    "Cannot migrate actor_professions row {$row->id}: duplicate Actor profession exists."
                );
            }

            DB::table('actor_professions')->where('id', $row->id)->update([
                'actor_id' => $actorId,
                'updated_at' => now(),
            ]);
        });

        Schema::table('actor_professions', function (Blueprint $table): void {
            $table->dropUnique('actor_professions_actor_profession_unique');
            $table->dropIndex('actor_professions_actorable_idx');
            $table->dropColumn(['actorable_type', 'actorable_id']);
        });

        Schema::table('actor_professions', function (Blueprint $table): void {
            $table->unsignedBigInteger('actor_id')->nullable(false)->change();
            $table->foreign('actor_id')->references('id')->on('actors')->restrictOnDelete();
            $table->unique(
                ['actor_id', 'profession_id'],
                'actor_professions_actor_profession_unique'
            );
        });
    }

    private function alignBusinesses(): void
    {
        if (! Schema::hasTable('businesses') || ! Schema::hasColumn('businesses', 'owner_user_id')) {
            return;
        }

        Schema::table('businesses', function (Blueprint $table): void {
            $table->unsignedBigInteger('owner_actor_id')->nullable()->after('code');
        });

        $this->backfillActorColumn('businesses', 'owner_user_id', 'owner_actor_id');

        Schema::table('businesses', function (Blueprint $table): void {
            $table->dropForeign(['owner_user_id']);
            $table->dropColumn('owner_user_id');
        });

        Schema::table('businesses', function (Blueprint $table): void {
            $table->unsignedBigInteger('owner_actor_id')->nullable(false)->change();
            $table->foreign('owner_actor_id')->references('id')->on('actors')->restrictOnDelete();
        });
    }

    private function alignBusinessMemberships(): void
    {
        if (
            ! Schema::hasTable('business_memberships')
            || ! Schema::hasColumn('business_memberships', 'user_id')
        ) {
            return;
        }

        Schema::table('business_memberships', function (Blueprint $table): void {
            $table->unsignedBigInteger('actor_id')->nullable()->after('business_id');
        });

        $this->backfillActorColumn('business_memberships', 'user_id', 'actor_id');

        Schema::table('business_memberships', function (Blueprint $table): void {
            $table->dropUnique(['business_id', 'user_id']);
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });

        Schema::table('business_memberships', function (Blueprint $table): void {
            $table->unsignedBigInteger('actor_id')->nullable(false)->change();
            $table->foreign('actor_id')->references('id')->on('actors')->restrictOnDelete();
            $table->unique(['business_id', 'actor_id']);
        });
    }

    private function backfillActorColumn(string $table, string $userColumn, string $actorColumn): void
    {
        DB::table($table)->orderBy('id')->get()->each(function (object $row) use ($table, $userColumn, $actorColumn): void {
            $actorId = DB::table('actors')->where('user_id', $row->{$userColumn})->value('id');

            if (! $actorId) {
                throw new RuntimeException(
                    "Cannot migrate {$table} row {$row->id}: User {$row->{$userColumn}} has no Actor."
                );
            }

            DB::table($table)->where('id', $row->id)->update([
                $actorColumn => $actorId,
                'updated_at' => now(),
            ]);
        });
    }
};
