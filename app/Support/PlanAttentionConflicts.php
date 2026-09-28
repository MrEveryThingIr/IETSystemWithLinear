<?php

namespace App\Support;

use App\Models\Context;
use App\Models\PlanOccurrence;
use App\PlanAttentionMode;
use App\PlanOccurrenceStatus;
use App\PlanTimingMode;
use Carbon\CarbonInterface;

final class PlanAttentionConflicts
{
    public function firstForWindow(
        Context $context,
        CarbonInterface $start,
        CarbonInterface $end,
        ?int $exceptPlanId = null,
    ): ?PlanOccurrence {
        return PlanOccurrence::query()
            ->with('plan')
            ->where('status', '!=', PlanOccurrenceStatus::Cancelled->value)
            ->where('scheduled_start_at', '<', $end->utc())
            ->where('scheduled_end_at', '>', $start->utc())
            ->whereHas('plan', function ($query) use ($context, $exceptPlanId): void {
                $query
                    ->where('context_id', $context->id)
                    ->where('metadata->planning_studio', 'baseline')
                    ->where('attention_mode', PlanAttentionMode::Exclusive->value);

                if ($exceptPlanId !== null) {
                    $query->where('id', '!=', $exceptPlanId);
                }
            })
            ->whereHas(
                'scheduleRule',
                fn ($query) => $query->where('timing_mode', PlanTimingMode::Fixed->value),
            )
            ->orderBy('scheduled_start_at')
            ->first();
    }
}
