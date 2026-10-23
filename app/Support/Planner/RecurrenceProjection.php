<?php

namespace App\Support\Planner;

use App\Actions\Planner\MaterializePlanOccurrences;
use App\Models\PlanScheduleRule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;

/** Counts distant recurrences without writing occurrence rows or expanding the UI. */
final class RecurrenceProjection
{
    /** @return array{expected: int, materialized: int, virtual: int} */
    public function summarize(User $user, PlanScheduleRule $rule, CarbonImmutable $from, CarbonImmutable $through): array
    {
        Gate::forUser($user)->authorize('view', $rule->plan);
        $from = $from->setTimezone($rule->timezone)->startOfDay();
        $through = $through->setTimezone($rule->timezone)->endOfDay();
        abort_if($through->lt($from) || $from->diffInDays($through) > 3660, 422, 'Choose a projection horizon of at most ten years.');
        $start = CarbonImmutable::parse($rule->starts_on->format('Y-m-d'), $rule->timezone)->startOfDay();
        $end = $rule->ends_on === null ? $through : CarbonImmutable::parse($rule->ends_on->format('Y-m-d'), $rule->timezone)->endOfDay()->min($through);
        $matcher = app(MaterializePlanOccurrences::class);
        $count = 0;
        $ordinal = 0;
        // With a finite limit, count from its anchor so late queries retain the original ordinal.
        $cursor = $rule->occurrence_limit === null ? $start->max($from) : $start;
        for (; $cursor->lte($end); $cursor = $cursor->addDay()) {
            if (! $matcher->matches($rule, $start, $cursor)) {
                continue;
            }
            $ordinal++;
            if ($rule->occurrence_limit !== null && $ordinal > $rule->occurrence_limit) {
                break;
            }
            if ($cursor->gte($from)) {
                $count++;
            }
        }
        $stored = $rule->occurrences()->whereBetween('local_date', [$from->toDateString(), $through->toDateString()])->count();

        return ['expected' => $count, 'materialized' => $stored, 'virtual' => max(0, $count - $stored)];
    }
}
