<?php

namespace App\Support\Planner;

use App\Models\Plan;
use App\Models\Planner\OccurrenceParticipation;
use App\Models\PlanOccurrence;
use App\Models\User;
use App\PlanOccurrenceStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;

/** Descriptive, actor-specific observations, never a personality or hiring score. */
final class CommitmentFactors
{
    /** @param list<int> $planIds @return array<string, mixed> */
    public function measure(User $user, array $planIds, CarbonImmutable $from, CarbonImmutable $through): array
    {
        $actor = LabAuthority::actor($user);
        abort_if($through->lt($from) || $from->diffInDays($through) > 3660 || count($planIds) > 100, 422);
        $plans = Plan::query()->whereIn('id', array_unique($planIds))->withCount(['participants' => fn ($q) => $q->where('status', 'active')])->get();
        abort_unless($plans->count() === count(array_unique($planIds)), 404);
        foreach ($plans as $plan) {
            Gate::forUser($user)->authorize('view', $plan);
        }
        $eligible = $plans->filter(fn (Plan $plan): bool => $plan->participants()->where('actor_id', $actor->id)->where('status', 'active')->exists());
        $query = PlanOccurrence::query()->whereIn('plan_id', $eligible->modelKeys())
            ->whereBetween('scheduled_start_at', [$from->utc(), $through->utc()])->orderBy('scheduled_start_at');
        abort_if((clone $query)->count() > 10000, 422, 'Choose a smaller measurement period.');
        $occurrences = $query->get();
        $participations = OccurrenceParticipation::query()->whereIn('plan_occurrence_id', $occurrences->modelKeys())->where('actor_id', $actor->id)->get()->keyBy('plan_occurrence_id');
        $rows = [];
        foreach ($occurrences as $occurrence) {
            $participant = $participations->get($occurrence->id);
            $plan = $eligible->firstWhere('id', $occurrence->plan_id);
            $excluded = $occurrence->status === PlanOccurrenceStatus::Cancelled
                || ($occurrence->status === PlanOccurrenceStatus::Skipped && in_array($occurrence->evaluated_outcome, [null, 'cancelled'], true));
            // Legacy completion of a shared occurrence does not prove this actor completed it.
            $legacyCompleted = $participant === null && $occurrence->status === PlanOccurrenceStatus::Completed
                && $occurrence->events()->where('event_type', 'completed')->where('actor_id', $actor->id)->exists();
            $legacyStarted = $legacyCompleted && $occurrence->events()->where('event_type', 'started')->where('actor_id', $actor->id)->exists();
            $start = $participant->actual_start_at ?? ($legacyStarted ? $occurrence->actual_start_at : null);
            $end = $participant->actual_end_at ?? ($legacyCompleted ? $occurrence->actual_end_at : null);
            $outcome = $excluded ? 'excluded' : ($participant->outcome ?? ($legacyCompleted ? 'completed'
                : ($occurrence->scheduled_start_at->isFuture() ? 'upcoming' : ($occurrence->window_end_at->isFuture() ? 'pending' : 'unconfirmed'))));
            $seconds = $participant->active_seconds ?? ($start !== null && $end !== null ? (int) $start->diffInSeconds($end) : null);
            $rows[] = [
                'occurrence_uuid' => $occurrence->uuid, 'plan_uuid' => $plan->uuid,
                'commitment_to' => $plan->participants_count > 1 || $plan->origin_type !== null ? 'others' : 'self',
                'target_start' => $occurrence->scheduled_start_at->toIso8601String(),
                'target_end' => $occurrence->scheduled_end_at->toIso8601String(),
                'window_start' => $occurrence->window_start_at->toIso8601String(),
                'window_end' => $occurrence->window_end_at->toIso8601String(),
                'actual_start' => $start?->toIso8601String(), 'actual_end' => $end?->toIso8601String(),
                'target_seconds' => (int) $occurrence->scheduled_start_at->diffInSeconds($occurrence->scheduled_end_at),
                'active_seconds' => $seconds,
                'start_deviation_seconds' => $start === null ? null : (int) $occurrence->scheduled_start_at->diffInSeconds($start, false),
                'finish_deviation_seconds' => $end === null ? null : (int) $occurrence->scheduled_end_at->diffInSeconds($end, false),
                'start_within_window' => $start === null ? null : $start->betweenIncluded($occurrence->window_start_at, $occurrence->window_end_at),
                'acknowledged' => $participant?->acknowledged_at !== null, 'attended' => $participant?->attended_at !== null,
                'phase' => $participant->phase ?? 'not_started', 'outcome' => $outcome,
                'confidence' => $participant?->outcome !== null || $legacyCompleted ? 'self_reported' : 'unconfirmed',
                'policy_version' => data_get($occurrence->policy_snapshot, 'version', 0),
                'provenance' => $legacyCompleted ? 'legacy_actor_event' : ($participant === null ? 'schedule_only' : 'participant_report'),
            ];
        }
        $counts = array_count_values(array_column($rows, 'outcome'));
        $reported = array_sum(array_intersect_key($counts, array_flip(['completed', 'partial', 'interrupted', 'missed'])));

        return [
            'subject_actor_id' => $actor->id, 'from' => $from->utc()->toIso8601String(), 'through' => $through->utc()->toIso8601String(),
            'expected' => count($rows) - ($counts['excluded'] ?? 0) - ($counts['upcoming'] ?? 0),
            'reported' => $reported, 'counts' => $counts,
            'completed_of_reported_percent' => $reported === 0 ? null : round(100 * ($counts['completed'] ?? 0) / $reported, 1),
            'rows' => $rows, 'generated_at' => now()->utc()->toIso8601String(),
            'qualification' => 'Descriptive self-reports; unconfirmed is not missed. No independent verification or personality inference.',
        ];
    }

    /** @return list<int> Unique definitions beneath a root; every node is independently authorized. */
    public function hierarchy(User $user, Plan $root): array
    {
        $ids = [];
        $queue = [$root];
        while ($plan = array_shift($queue)) {
            Gate::forUser($user)->authorize('view', $plan);
            if (in_array($plan->id, $ids, true)) {
                continue;
            }
            abort_if(count($ids) >= 100, 422, 'Choose a smaller routine.');
            $ids[] = $plan->id;
            foreach ($plan->childPlans()->get() as $child) {
                $queue[] = $child;
            }
        }

        return $ids;
    }
}
