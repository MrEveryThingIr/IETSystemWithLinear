<?php

namespace App\Actions\Planner;

use App\Models\PlanScheduleRule;
use App\PlanScheduleRuleStatus;
use App\PlanStatus;

class MaterializePlannerHorizon
{
    public function __construct(private readonly MaterializePlanOccurrences $materialize) {}

    public function execute(int $days = 120): int
    {
        abort_if($days < 1 || $days > 730, 422, 'Planner materialization horizon must be between 1 and 730 days.');

        $created = 0;
        $from = now()->subDay();
        $through = now()->addDays($days);

        PlanScheduleRule::query()
            ->where('status', PlanScheduleRuleStatus::Active)
            ->whereHas('plan', fn ($query) => $query->where('status', PlanStatus::Active))
            ->orderBy('id')
            ->chunkById(100, function ($rules) use (&$created, $from, $through): void {
                foreach ($rules as $rule) {
                    $created += $this->materialize->execute($rule, $from, $through);
                }
            });

        return $created;
    }
}
