<?php

namespace App\Actions\Planner;

use App\Models\Planner\ExecutionPolicy;
use App\Models\Planner\ExecutionSegment;
use App\Models\Planner\OccurrenceParticipation;
use App\Models\PlanOccurrence;
use App\Models\PlanOccurrenceEvent;
use App\Models\User;
use App\PlanOccurrenceEventType;
use App\PlanOccurrenceStatus;
use App\PlanStatus;
use App\Support\Planner\LabAuthority;
use App\Support\TemporalPreferences;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

/** Authorized participant facts; coarse legacy status never substitutes for evaluated outcome. */
final class ExecuteLivingOccurrence
{
    /** @param array<string, mixed> $payload */
    public function execute(User $user, PlanOccurrence $occurrence, string $action, string $key, array $payload = []): PlanOccurrence
    {
        $actor = LabAuthority::actor($user);
        Gate::forUser($user)->authorize($action === 'cancel' ? 'manage' : 'participate', $occurrence->plan);
        abort_unless(strlen($key) >= 8 && strlen($key) <= 100, 422, 'Supply a stable retry identifier.');
        $types = [
            'acknowledge' => PlanOccurrenceEventType::Acknowledged, 'attend' => PlanOccurrenceEventType::AttendanceRecorded,
            'start' => PlanOccurrenceEventType::Started, 'pause' => PlanOccurrenceEventType::Paused,
            'resume' => PlanOccurrenceEventType::Resumed, 'finish' => PlanOccurrenceEventType::Completed,
            'report' => PlanOccurrenceEventType::OutcomeReported, 'correct' => PlanOccurrenceEventType::OutcomeCorrected,
            'cancel' => PlanOccurrenceEventType::Cancelled,
        ];
        abort_unless(isset($types[$action]), 422);
        ksort($payload);
        $fingerprint = hash('sha256', json_encode([$action, $payload], JSON_THROW_ON_ERROR));
        $retryKey = $actor->id.':'.$key;

        return DB::transaction(function () use ($user, $occurrence, $action, $actor, $retryKey, $payload, $fingerprint, $types): PlanOccurrence {
            $locked = PlanOccurrence::query()->with(['plan', 'scheduleRule'])->lockForUpdate()->findOrFail($occurrence->id);
            Gate::forUser($user)->authorize($action === 'cancel' ? 'manage' : 'participate', $locked->plan);
            $previous = $locked->events()->where('idempotency_key', $retryKey)->first();
            if ($previous !== null) {
                abort_unless(data_get($previous->payload, 'fingerprint') === $fingerprint, 409, 'A retry identifier cannot describe a different action.');

                return $locked;
            }
            abort_unless($locked->plan->status === PlanStatus::Active, 422, 'This plan is not active.');
            $now = CarbonImmutable::now('UTC');
            $snapshot = $locked->policy_snapshot ?? ExecutionPolicy::snapshot($locked->scheduleRule);
            $locked->applyLiving(['policy_snapshot' => $snapshot]);
            $eventPayload = ['action' => $action, 'fingerprint' => $fingerprint];
            $effective = $now;

            if ($action === 'cancel') {
                abort_unless(in_array($locked->status, [PlanOccurrenceStatus::Scheduled, PlanOccurrenceStatus::InProgress], true), 422);
                $this->closeAllSegments($locked, $now, 'cancelled');
                $locked->transition(PlanOccurrenceStatus::Cancelled, $locked->actual_start_at, $locked->actual_start_at === null ? null : $now);
                $locked->applyLiving(['execution_phase' => 'finished', 'evaluated_outcome' => 'cancelled', 'evidence_confidence' => 'self_reported']);
            } else {
                abort_unless($locked->plan->participants()->where('actor_id', $actor->id)->where('status', 'active')->exists(), 403);
                abort_if($locked->status === PlanOccurrenceStatus::Cancelled, 422, 'A cancelled occurrence cannot acquire new execution.');
                $participant = OccurrenceParticipation::query()->firstOrCreate(['plan_occurrence_id' => $locked->id, 'actor_id' => $actor->id]);
                if ($action === 'acknowledge' || $action === 'attend') {
                    $field = $action === 'acknowledge' ? 'acknowledged_at' : 'attended_at';
                    if ($participant->{$field} !== null) {
                        return $locked;
                    }
                    $participant->update([$field => $now]);
                } elseif ($action === 'start' || $action === 'resume') {
                    abort_unless($participant->phase === ($action === 'start' ? 'not_started' : 'paused'), 422, 'Execution cannot start from this phase.');
                    $this->assertStartAllowed($locked, $now, $snapshot);
                    abort_unless($locked->prerequisitesSatisfied(), 422, 'Required preparation is incomplete.');
                    $participant->update(['phase' => 'running', 'actual_start_at' => $participant->actual_start_at ?? $now]);
                    ExecutionSegment::query()->create(['plan_occurrence_id' => $locked->id, 'actor_id' => $actor->id, 'started_at' => $now]);
                } elseif ($action === 'pause' || $action === 'finish') {
                    abort_unless(in_array($participant->phase, $action === 'pause' ? ['running'] : ['running', 'paused', 'awaiting_report'], true), 422, 'Execution is not running or paused.');
                    $this->closeSegment($locked, $participant, $now, $action);
                    if ($action === 'pause') {
                        $participant->update(['phase' => 'paused']);
                    } else {
                        $outcome = (string) ($payload['outcome'] ?? 'completed');
                        abort_unless(in_array($outcome, ['completed', 'partial', 'interrupted'], true), 422);
                        $participant->update(['phase' => 'finished', 'outcome' => $outcome, 'actual_end_at' => $now]);
                        $eventPayload['outcome'] = $outcome;
                    }
                } else {
                    abort_if($now->gt($locked->window_end_at->addDays($snapshot['reporting_days'])), 422, 'The reporting period has closed.');
                    $data = Validator::make($payload, [
                        'outcome' => ['required', 'in:completed,partial,interrupted,missed'],
                        'actual_start_at' => ['nullable', 'date'], 'actual_end_at' => ['nullable', 'date'],
                        'reason' => [$action === 'correct' ? 'required' : 'nullable', 'string', 'max:1000'],
                        'supersedes' => [$action === 'correct' ? 'required' : 'nullable', 'uuid'],
                    ])->validate();
                    if ($action === 'correct') {
                        $latest = $locked->events()->where('actor_id', $actor->id)->whereIn('event_type', [
                            PlanOccurrenceEventType::OutcomeReported->value, PlanOccurrenceEventType::OutcomeCorrected->value,
                            PlanOccurrenceEventType::Completed->value,
                        ])->reorder()->latest('id')->first();
                        abort_unless($latest !== null && $latest->uuid === $data['supersedes'], 409, 'Correct the latest report and preserve the prior event.');
                    } else {
                        abort_if($participant->phase === 'finished', 409, 'Use a correction to change a finished report.');
                    }
                    $start = $end = null;
                    if ($data['outcome'] !== 'missed') {
                        abort_unless(! empty($data['actual_start_at']) && ! empty($data['actual_end_at']), 422, 'Report actual start and finish times.');
                        $timezone = TemporalPreferences::timezoneFor($user);
                        $start = CarbonImmutable::parse($data['actual_start_at'], $timezone)->utc();
                        $end = CarbonImmutable::parse($data['actual_end_at'], $timezone)->utc();
                        abort_if($end->lt($start) || $end->gt($now) || $start->diffInDays($end) > 7, 422, 'Reported execution times are invalid.');
                        $effective = $end;
                    } else {
                        abort_unless(empty($data['actual_start_at']) && empty($data['actual_end_at']), 422, 'A missed report cannot also claim execution.');
                    }
                    $this->closeSegment($locked, $participant, $now, 'reported');
                    $participant->update(['phase' => 'finished', 'outcome' => $data['outcome'], 'confidence' => 'self_reported',
                        'actual_start_at' => $start, 'actual_end_at' => $end, 'active_seconds' => $start === null ? 0 : (int) $start->diffInSeconds($end)]);
                    $eventPayload += ['outcome' => $data['outcome'], 'actual_start_at' => $start?->toIso8601String(),
                        'actual_end_at' => $end?->toIso8601String(), 'supersedes' => $data['supersedes'] ?? null, 'reason' => $data['reason'] ?? null];
                }
                $this->project($locked);
            }
            PlanOccurrenceEvent::query()->create(['plan_occurrence_id' => $locked->id, 'actor_id' => $actor->id,
                'event_type' => $types[$action], 'payload' => $eventPayload, 'effective_at' => $effective,
                'created_at' => $now, 'source' => 'user', 'idempotency_key' => $retryKey]);

            return $locked->fresh();
        }, attempts: 3);
    }

    /** @param array{window_style: string, reporting_days: int, version: int} $policy */
    private function assertStartAllowed(PlanOccurrence $occurrence, CarbonImmutable $now, array $policy): void
    {
        $earliest = $policy['window_style'] === 'deadline' ? CarbonImmutable::parse($occurrence->created_at) : $occurrence->window_start_at;
        abort_if($now->lt($earliest), 422, 'This execution window has not opened.');
        if ($policy['window_style'] === 'hard') {
            abort_if($now->gt($occurrence->window_end_at), 422, 'This hard execution window has closed. Report an outcome instead.');
        } else {
            abort_if($now->gt($occurrence->window_end_at->addDays($policy['reporting_days'])), 422, 'The late execution period has closed.');
        }
    }

    public function closeSegment(PlanOccurrence $occurrence, OccurrenceParticipation $participant, CarbonImmutable $end, string $reason): void
    {
        $segments = ExecutionSegment::query()->where('plan_occurrence_id', $occurrence->id)->where('actor_id', $participant->actor_id)->whereNull('ended_at')->get();
        abort_if($segments->count() > 1, 409, 'Conflicting open execution segments require review.');
        foreach ($segments as $segment) {
            $stop = $end->max($segment->started_at);
            $segment->update(['ended_at' => $stop, 'closure_reason' => $reason]);
            $participant->active_seconds += (int) $segment->started_at->diffInSeconds($stop);
            $participant->save();
        }
    }

    public function closeAllSegments(PlanOccurrence $occurrence, CarbonImmutable $end, string $reason): void
    {
        foreach (OccurrenceParticipation::query()->where('plan_occurrence_id', $occurrence->id)->get() as $participant) {
            $this->closeSegment($occurrence, $participant, $end, $reason);
        }
    }

    public function project(PlanOccurrence $occurrence): void
    {
        $rows = OccurrenceParticipation::query()->where('plan_occurrence_id', $occurrence->id)->get();
        $expected = $occurrence->plan->participants()->where('status', 'active')->count();
        $finished = $rows->where('phase', 'finished');
        $allFinished = $expected > 0 && $finished->count() === $expected;
        $phase = $rows->contains('phase', 'running') ? 'running' : ($rows->contains('phase', 'paused') ? 'paused' : ($allFinished ? 'finished' : ($occurrence->window_end_at->isPast() ? 'awaiting_report' : 'not_started')));
        $outcome = 'unconfirmed';
        if ($allFinished) {
            $values = $finished->pluck('outcome');
            $outcome = $values->every(fn ($value): bool => $value === 'completed') ? 'completed'
                : ($values->every(fn ($value): bool => $value === 'missed') ? 'missed'
                    : ($values->contains('completed') || $values->contains('partial') ? 'partial' : 'interrupted'));
        }
        $start = $rows->whereNotNull('actual_start_at')->min('actual_start_at');
        $end = $allFinished ? $rows->whereNotNull('actual_end_at')->max('actual_end_at') : null;
        if ($start !== null && $occurrence->status === PlanOccurrenceStatus::Scheduled) {
            $occurrence->transition(PlanOccurrenceStatus::InProgress, $start);
        }
        if ($allFinished && $occurrence->status === PlanOccurrenceStatus::InProgress) {
            $occurrence->transition(PlanOccurrenceStatus::Completed, $start, $end, now());
        } elseif ($allFinished && $outcome === 'missed' && $occurrence->status === PlanOccurrenceStatus::Scheduled) {
            $occurrence->transition(PlanOccurrenceStatus::Skipped);
        }
        $occurrence->applyLiving(['execution_phase' => $phase, 'evaluated_outcome' => $outcome,
            'evidence_confidence' => $finished->isEmpty() ? 'unconfirmed' : 'self_reported', 'active_seconds' => (int) $rows->sum('active_seconds'),
            'actual_start_at' => $start, 'actual_end_at' => $end]);
    }
}
