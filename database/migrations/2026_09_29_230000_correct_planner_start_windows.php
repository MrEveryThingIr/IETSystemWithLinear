<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->rewriteScheduledWindowEnds(useScheduledStart: true);
    }

    public function down(): void
    {
        $this->rewriteScheduledWindowEnds(useScheduledStart: false);
    }

    private function rewriteScheduledWindowEnds(bool $useScheduledStart): void
    {
        DB::table('plan_occurrences')
            ->where('status', 'scheduled')
            ->whereNull('actual_start_at')
            ->orderBy('id')
            ->chunkById(250, function ($occurrences) use ($useScheduledStart): void {
                $ruleIds = $occurrences->pluck('schedule_rule_id')->unique()->values()->all();

                $rules = DB::table('plan_schedule_rules')
                    ->whereIn('id', $ruleIds)
                    ->pluck('window_after_minutes', 'id');

                foreach ($occurrences as $occurrence) {
                    $after = (int) ($rules[$occurrence->schedule_rule_id] ?? 0);
                    $anchor = $useScheduledStart
                        ? $occurrence->scheduled_start_at
                        : $occurrence->scheduled_end_at;

                    $windowEnd = CarbonImmutable::parse((string) $anchor, 'UTC')
                        ->addMinutes($after)
                        ->format('Y-m-d H:i:s');

                    DB::table('plan_occurrences')
                        ->where('id', $occurrence->id)
                        ->update(['window_end_at' => $windowEnd]);
                }
            }, 'id');
    }
};
