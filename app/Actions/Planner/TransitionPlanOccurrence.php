<?php

namespace App\Actions\Planner;

use App\Models\Actor;
use App\Models\PlanOccurrence;
use App\Models\PlanOccurrenceEvent;
use App\Models\User;
use App\PlanOccurrenceEventType;
use App\PlanOccurrenceStatus;
use App\PlanStatus;
use App\Support\Planner\OccurrenceTiming;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Everything a participant does with one occurrence. What is allowed, and
 * when, is decided by OccurrenceTiming; this class only records it, with an
 * event for every change so the history is never rewritten.
 */
class TransitionPlanOccurrence
{
    /** Why something was not done; chosen by the person, never inferred. */
    public const REASONS = ['forgot', 'overslept', 'tired', 'no_time', 'urgent', 'not_in_mood', 'unwell', 'plans_changed', 'other'];

    public const OUTCOMES = ['done', 'partly', 'not_done'];

    public function start(PlanOccurrence $occurrence, User $user): PlanOccurrence
    {
        return $this->act($occurrence, $user, 'start', function (PlanOccurrence $locked, CarbonImmutable $now): array {
            abort_unless($locked->prerequisitesSatisfied(), 422, __('planner.validation.prerequisites_incomplete'));
            $locked->transition(PlanOccurrenceStatus::InProgress, $now);

            return [PlanOccurrenceEventType::Started, ['actual_start_at' => $now->toIso8601String()]];
        });
    }

    /** Finish what was started, or mark it done now. */
    public function complete(PlanOccurrence $occurrence, User $user): PlanOccurrence
    {
        return $this->markDone($occurrence, $user);
    }

    public function markDone(PlanOccurrence $occurrence, User $user): PlanOccurrence
    {
        return $this->act($occurrence, $user, 'done', function (PlanOccurrence $locked, CarbonImmutable $now): array {
            $start = $locked->actual_start_at ?? $now;
            $locked->transition(PlanOccurrenceStatus::Completed, $start, $now, $now, ['reported_at' => $now]);

            return [PlanOccurrenceEventType::Completed, ['actual_start_at' => $start->toIso8601String(), 'actual_end_at' => $now->toIso8601String()]];
        });
    }

    /** "Not this time": before it starts this keeps the plan honest; later it means not done. */
    public function skip(PlanOccurrence $occurrence, User $user, ?string $reason = null, ?string $note = null): PlanOccurrence
    {
        $reason = $this->reason($reason);
        $note = $this->note($note);

        return $this->act($occurrence, $user, 'skip', function (PlanOccurrence $locked, CarbonImmutable $now) use ($reason, $note): array {
            $locked->transition(PlanOccurrenceStatus::Skipped, null, $locked->status === PlanOccurrenceStatus::InProgress ? $now : null, null, [
                'reason' => $reason, 'note' => $note, 'reported_at' => $now,
            ]);

            return [PlanOccurrenceEventType::Skipped, ['reason' => $reason]];
        });
    }

    public function cancel(PlanOccurrence $occurrence, User $user): PlanOccurrence
    {
        return $this->act($occurrence, $user, 'cancel', function (PlanOccurrence $locked, CarbonImmutable $now): array {
            $end = $locked->status === PlanOccurrenceStatus::InProgress ? $now : null;
            $locked->transition(PlanOccurrenceStatus::Cancelled, null, $end);

            return [PlanOccurrenceEventType::Cancelled, ['actual_end_at' => $end?->toIso8601String()]];
        });
    }

    /**
     * Tell what happened, now or afterwards (within the report period), or
     * change an earlier answer: done at a time, partly done, or not done
     * with a reason.
     *
     * @param  array{outcome: string, started_at?: ?CarbonImmutable, minutes?: ?int, percent?: ?int, reason?: ?string, note?: ?string, difficulty?: ?int}  $report
     */
    public function report(PlanOccurrence $occurrence, User $user, array $report): PlanOccurrence
    {
        $outcome = $report['outcome'];
        abort_unless(in_array($outcome, self::OUTCOMES, true), 422, __('habits.validation.outcome'));
        $difficulty = $report['difficulty'] ?? null;
        abort_if($difficulty !== null && ($difficulty < 1 || $difficulty > 5), 422, __('habits.validation.difficulty'));
        $note = $this->note($report['note'] ?? null);
        $reason = $outcome === 'not_done' ? $this->reason($report['reason'] ?? null) : null;
        $percent = $outcome === 'partly' ? (int) ($report['percent'] ?? 50) : null;
        abort_if($percent !== null && ($percent < 1 || $percent > 99), 422, __('habits.validation.percent'));

        $fresh = PlanOccurrence::query()->with(['plan', 'scheduleRule'])->findOrFail($occurrence->id);
        $correction = in_array($fresh->status, [PlanOccurrenceStatus::Completed, PlanOccurrenceStatus::Skipped], true);

        return $this->act($fresh, $user, $correction ? 'correct' : 'report', function (PlanOccurrence $locked, CarbonImmutable $now) use ($report, $outcome, $difficulty, $note, $reason, $percent, $correction): array {
            $told = ['completion_percent' => $percent, 'reason' => $reason, 'note' => $note, 'difficulty' => $difficulty, 'reported_at' => $now];

            if ($outcome === 'not_done') {
                $end = $locked->status === PlanOccurrenceStatus::InProgress ? $now : null;
                $locked->transition(PlanOccurrenceStatus::Skipped, null, $end, null, $told, $correction);
                $payload = ['outcome' => $outcome, 'reason' => $reason];
            } else {
                $start = $report['started_at'] ?? $locked->actual_start_at;
                abort_unless($start instanceof CarbonImmutable, 422, __('habits.validation.time_required'));
                $start = $start->utc()->startOfMinute();
                abort_if($start->greaterThan($now), 422, __('habits.validation.time_future'));
                $this->assertSameDay($locked, $start);
                $minutes = (int) ($report['minutes'] ?? $locked->scheduled_start_at->diffInMinutes($locked->scheduled_end_at));
                abort_if($minutes < 0 || $minutes > 1440, 422, __('habits.validation.minutes'));
                $end = $start->addMinutes($minutes);
                $end = $end->greaterThan($now) ? $now : $end;
                $locked->transition(PlanOccurrenceStatus::Completed, $start, $end, $now, $told, $correction);
                $payload = ['outcome' => $outcome, 'actual_start_at' => $start->toIso8601String(), 'actual_end_at' => $end->toIso8601String(), 'percent' => $percent];
            }

            return [$correction ? PlanOccurrenceEventType::Corrected : PlanOccurrenceEventType::Reported, $payload + ['difficulty' => $difficulty]];
        });
    }

    /**
     * @param  callable(PlanOccurrence, CarbonImmutable): array{0: PlanOccurrenceEventType, 1: array<string, mixed>}  $apply
     */
    private function act(PlanOccurrence $occurrence, User $user, string $action, callable $apply): PlanOccurrence
    {
        $current = $this->currentUser($user);

        return DB::transaction(function () use ($occurrence, $current, $action, $apply): PlanOccurrence {
            $locked = PlanOccurrence::query()
                ->with(['plan.prerequisites', 'prerequisiteChecks', 'scheduleRule'])
                ->lockForUpdate()
                ->findOrFail($occurrence->id);

            Gate::forUser($current)->authorize('participate', $locked->plan);
            // Reports about the past stay possible on a paused or ended plan; doing needs an active one.
            $planOpen = in_array($action, ['report', 'correct'], true)
                ? $locked->plan->status !== PlanStatus::Cancelled
                : $locked->plan->status === PlanStatus::Active;
            abort_unless($planOpen, 422, __('planner.validation.active_plan_required'));

            $now = CarbonImmutable::now('UTC');
            abort_unless(OccurrenceTiming::allows($locked, $action, $now), 422, __('habits.validation.not_now.'.$action));

            [$type, $payload] = $apply($locked, $now);
            PlanOccurrenceEvent::query()->create([
                'plan_occurrence_id' => $locked->id,
                'actor_id' => Actor::query()->findOrFail($current->actor->id)->id,
                'event_type' => $type,
                'payload' => array_filter($payload, fn ($value) => $value !== null),
            ]);

            return $locked->fresh(['plan.prerequisites', 'prerequisiteChecks', 'expenses.monetaryUnit', 'events', 'scheduleRule']);
        }, attempts: 3);
    }

    /** A time told for an occurrence belongs to its own day (an open one: any time since it was planned). */
    private function assertSameDay(PlanOccurrence $occurrence, CarbonImmutable $start): void
    {
        if (OccurrenceTiming::policyOf($occurrence) === OccurrenceTiming::OPEN) {
            abort_if($start->lessThan(CarbonImmutable::instance($occurrence->created_at)->subDay()), 422, __('habits.validation.time_day'));

            return;
        }
        $local = $start->setTimezone($occurrence->timezone)->toDateString();
        abort_unless($local === $occurrence->local_date->toDateString(), 422, __('habits.validation.time_day'));
    }

    private function reason(?string $reason): ?string
    {
        $reason = $reason === '' ? null : $reason;
        abort_if($reason !== null && ! in_array($reason, self::REASONS, true), 422, __('habits.validation.reason'));

        return $reason;
    }

    private function note(?string $note): ?string
    {
        $note = trim((string) $note);
        abort_if(mb_strlen($note) > 1000, 422, __('habits.validation.note'));

        return $note === '' ? null : $note;
    }

    private function currentUser(User $user): User
    {
        $current = User::query()->with('actor')->find($user->id);
        abort_unless(
            $current instanceof User
            && $current->status === 'active'
            && $current->email_verified_at !== null
            && $current->actor instanceof Actor
            && $current->actor->status === 'active',
            403,
        );

        return $current;
    }
}
