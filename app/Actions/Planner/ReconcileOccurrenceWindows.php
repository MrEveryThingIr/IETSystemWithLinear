<?php

namespace App\Actions\Planner;

use App\Models\Planner\ExecutionPolicy;
use App\Models\Planner\OccurrenceParticipation;
use App\Models\PlanOccurrence;
use App\Models\PlanOccurrenceEvent;
use App\PlanOccurrenceEventType;
use App\PlanOccurrenceStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class ReconcileOccurrenceWindows
{
    public function execute(int $limit = 500): int
    {
        abort_unless($limit >= 1 && $limit <= 10000, 422);
        $now = CarbonImmutable::now('UTC');
        $ids = PlanOccurrence::query()->whereNull('window_closed_at')->where('window_end_at', '<', $now)
            ->orderBy('window_end_at')->limit($limit)->pluck('id');
        $changed = 0;
        foreach ($ids as $id) {
            $changed += DB::transaction(function () use ($id, $now): int {
                $occurrence = PlanOccurrence::query()->with(['plan', 'scheduleRule'])->lockForUpdate()->findOrFail($id);
                if ($occurrence->window_closed_at !== null || $occurrence->window_end_at->gte($now)) {
                    return 0;
                }
                $snapshot = $occurrence->policy_snapshot ?? ExecutionPolicy::snapshot($occurrence->scheduleRule);
                if ($snapshot['window_style'] === 'hard') {
                    app(ExecuteLivingOccurrence::class)->closeAllSegments($occurrence, $occurrence->window_end_at, 'window_elapsed');
                    OccurrenceParticipation::query()->where('plan_occurrence_id', $occurrence->id)->where('phase', 'running')
                        ->update(['phase' => 'awaiting_report']);
                }
                // A missing report is never proof that the person did not perform.
                $outcome = $occurrence->evaluated_outcome ?? match ($occurrence->status) {
                    PlanOccurrenceStatus::Completed => 'completed',
                    PlanOccurrenceStatus::Cancelled, PlanOccurrenceStatus::Skipped => 'cancelled',
                    default => 'unconfirmed',
                };
                $occurrence->applyLiving(['window_closed_at' => $occurrence->window_end_at,
                    'policy_snapshot' => $snapshot, 'evaluated_outcome' => $outcome,
                    'evidence_confidence' => $occurrence->evidence_confidence ?? ($outcome === 'unconfirmed' ? 'unconfirmed' : 'self_reported')]);
                if ($snapshot['window_style'] === 'hard') {
                    app(ExecuteLivingOccurrence::class)->project($occurrence);
                }
                PlanOccurrenceEvent::query()->create(['plan_occurrence_id' => $occurrence->id, 'actor_id' => null,
                    'event_type' => PlanOccurrenceEventType::WindowClosed, 'effective_at' => $occurrence->window_end_at,
                    'created_at' => $now, 'source' => 'scheduler', 'idempotency_key' => 'system:window-closed',
                    'payload' => ['outcome' => $outcome, 'policy_version' => $snapshot['version']]]);

                return 1;
            }, attempts: 3);
        }

        return $changed;
    }
}
