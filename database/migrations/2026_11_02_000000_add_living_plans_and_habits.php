<?php

use App\Support\Planner\OccurrenceTiming;
use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Plans that know what time it is, expected-versus-actual reports, habits and
 * tuning periods. Additive: existing rows keep every value; the new
 * close_policy and closes_at are filled from each plan's kind and times.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plan_schedule_rules', function (Blueprint $table): void {
            $table->string('close_policy', 8)->nullable();
        });

        Schema::table('plan_occurrences', function (Blueprint $table): void {
            // When doing it stops being possible (null: stays open until handled).
            $table->dateTime('closes_at')->nullable();
            // What the person told: how much (partly), why not, how hard, when told.
            $table->unsignedTinyInteger('completion_percent')->nullable();
            $table->string('reason', 24)->nullable();
            $table->text('note')->nullable();
            $table->unsignedTinyInteger('difficulty')->nullable();
            $table->dateTime('reported_at')->nullable();
            $table->index(['status', 'closes_at'], 'plan_occ_status_closes_ix');
        });

        Schema::create('plan_habits', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('plan_id')->unique()->constrained('plans')->restrictOnDelete();
            $table->foreignId('created_by_actor_id')->constrained('actors')->restrictOnDelete();
            // build: a new routine at an expected time; replace: do something else when the urge comes.
            $table->string('mode', 12)->default('build');
            $table->string('cue', 300)->nullable();
            $table->string('reward', 300)->nullable();
            $table->string('why', 500)->nullable();
            $table->string('replaces', 300)->nullable();
            $table->timestamps();
        });

        Schema::create('plan_periods', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('plan_id')->constrained('plans')->restrictOnDelete();
            $table->unsignedSmallInteger('number');
            // The one thing that is different in this period ("clothes ready the night before").
            $table->string('focus', 300);
            // An in-between target on the way to the ideal time (null: the plan's own time).
            $table->string('target_time', 5)->nullable();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->date('ended_on')->nullable();
            $table->string('verdict', 12)->nullable();
            $table->text('reflection')->nullable();
            $table->json('automaticity')->nullable();
            $table->timestamps();
            $table->unique(['plan_id', 'number'], 'plan_periods_number_uq');
            $table->index(['plan_id', 'starts_on'], 'plan_periods_dates_ix');
        });

        Schema::create('plan_urges', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('plan_id')->constrained('plans')->restrictOnDelete();
            $table->foreignId('period_id')->nullable()->constrained('plan_periods')->nullOnDelete();
            $table->foreignId('actor_id')->constrained('actors')->restrictOnDelete();
            $table->dateTime('occurred_at');
            // replaced: did the new routine instead; gave_in: the old habit won this time.
            $table->string('outcome', 12);
            $table->unsignedTinyInteger('strength')->nullable();
            $table->string('note', 300)->nullable();
            $table->dateTime('created_at');
            $table->index(['plan_id', 'occurred_at'], 'plan_urges_plan_time_ix');
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_urges');
        Schema::dropIfExists('plan_periods');
        Schema::dropIfExists('plan_habits');
        Schema::table('plan_occurrences', function (Blueprint $table): void {
            $table->dropIndex('plan_occ_status_closes_ix');
            $table->dropColumn(['closes_at', 'completion_percent', 'reason', 'note', 'difficulty', 'reported_at']);
        });
        Schema::table('plan_schedule_rules', function (Blueprint $table): void {
            $table->dropColumn('close_policy');
        });
    }

    private function backfill(): void
    {
        $kinds = [];
        $kindOf = function (int $planId) use (&$kinds): ?string {
            if (! array_key_exists($planId, $kinds)) {
                $metadata = json_decode((string) DB::table('plans')->where('id', $planId)->value('metadata'), true);
                $kinds[$planId] = is_array($metadata) && is_string($metadata['kind'] ?? null) ? $metadata['kind'] : null;
            }

            return $kinds[$planId];
        };

        $policies = [];
        DB::table('plan_schedule_rules')->orderBy('id')->select(['id', 'plan_id'])->chunkById(200, function ($rules) use (&$policies, $kindOf): void {
            foreach ($rules as $rule) {
                $policy = OccurrenceTiming::policyForKind($kindOf((int) $rule->plan_id));
                $policies[(int) $rule->id] = $policy;
                DB::table('plan_schedule_rules')->where('id', $rule->id)->update(['close_policy' => $policy]);
            }
        });

        $zones = array_flip(timezone_identifiers_list());
        DB::table('plan_occurrences')->orderBy('id')
            ->select(['id', 'plan_id', 'schedule_rule_id', 'scheduled_start_at', 'window_end_at', 'timezone'])
            ->chunkById(500, function ($rows) use ($policies, $kindOf, $zones): void {
                foreach ($rows as $row) {
                    $policy = $policies[(int) $row->schedule_rule_id] ?? OccurrenceTiming::policyForKind($kindOf((int) $row->plan_id));
                    $timezone = isset($zones[$row->timezone]) ? $row->timezone : 'UTC';
                    $closes = OccurrenceTiming::closesAt(
                        $policy,
                        CarbonImmutable::parse($row->scheduled_start_at, 'UTC'),
                        CarbonImmutable::parse($row->window_end_at, 'UTC'),
                        $timezone,
                    );
                    if ($closes !== null) {
                        DB::table('plan_occurrences')->where('id', $row->id)->update(['closes_at' => $closes->format('Y-m-d H:i:s')]);
                    }
                }
            });
    }
};
