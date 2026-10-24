<?php

namespace App\Livewire\Planner\Concerns;

use App\Actions\Planner\TransitionPlanOccurrence;
use App\Models\PlanOccurrence;
use App\Models\User;
use App\Support\Planner\OccurrenceTiming;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The small "how did it go?" form, shared by the plan page and Today:
 * done at a time, partly, or not done with a reason; optionally how hard
 * it was and a note. Also the one-tap Done and Skip.
 */
trait ReportsOccurrences
{
    public ?string $reportUuid = null;

    public string $reportOutcome = 'done';

    public string $reportTime = '';

    public string $reportDate = '';

    public string $reportMinutes = '';

    public int $reportPercent = 50;

    public string $reportReason = '';

    public string $reportNote = '';

    public ?int $reportDifficulty = null;

    public function openReport(string $uuid, string $outcome = 'done'): void
    {
        $occurrence = $this->reportable($uuid);
        $this->resetErrorBag('report');
        $this->reportUuid = $occurrence->uuid;
        $this->reportOutcome = in_array($outcome, TransitionPlanOccurrence::OUTCOMES, true) ? $outcome : 'done';
        $timezone = $occurrence->timezone;
        $start = $occurrence->actual_start_at ?? $occurrence->scheduled_start_at;
        $this->reportDate = $start->setTimezone($timezone)->toDateString();
        $this->reportTime = $start->setTimezone($timezone)->format('H:i');
        $minutes = $occurrence->actual_start_at !== null && $occurrence->actual_end_at !== null
            ? (int) $occurrence->actual_start_at->diffInMinutes($occurrence->actual_end_at)
            : (int) $occurrence->scheduled_start_at->diffInMinutes($occurrence->scheduled_end_at);
        $this->reportMinutes = (string) $minutes;
        $this->reportPercent = $occurrence->completion_percent ?? 50;
        $this->reportReason = (string) $occurrence->reason;
        $this->reportNote = (string) $occurrence->note;
        $this->reportDifficulty = $occurrence->difficulty;
    }

    public function closeReport(): void
    {
        $this->reset('reportUuid', 'reportReason', 'reportNote', 'reportDifficulty');
        $this->resetErrorBag('report');
    }

    public function saveReport(TransitionPlanOccurrence $transition): void
    {
        abort_unless($this->reportUuid !== null, 422);
        $occurrence = $this->reportable($this->reportUuid);
        $this->validate([
            'reportOutcome' => ['required', 'in:'.implode(',', TransitionPlanOccurrence::OUTCOMES)],
            'reportTime' => ['exclude_if:reportOutcome,not_done', 'required', 'date_format:H:i'],
            'reportDate' => ['exclude_if:reportOutcome,not_done', 'required', 'date_format:Y-m-d'],
            'reportMinutes' => ['exclude_if:reportOutcome,not_done', 'nullable', 'integer', 'min:0', 'max:1440'],
            'reportPercent' => ['integer', 'min:1', 'max:99'],
            'reportReason' => ['nullable', 'in:'.implode(',', TransitionPlanOccurrence::REASONS)],
            'reportNote' => ['nullable', 'string', 'max:1000'],
            'reportDifficulty' => ['nullable', 'integer', 'min:1', 'max:5'],
        ], [], [
            'reportTime' => __('habits.report.started_at'),
            'reportMinutes' => __('habits.report.minutes'),
        ]);

        $startedAt = null;
        if ($this->reportOutcome !== 'not_done') {
            $startedAt = CarbonImmutable::parse($this->reportDate.' '.$this->reportTime, $occurrence->timezone)->utc();
        }

        $this->runPlannerAction(fn () => $transition->report($occurrence, $this->reportingUser(), [
            'outcome' => $this->reportOutcome,
            'started_at' => $startedAt,
            'minutes' => $this->reportMinutes === '' ? null : (int) $this->reportMinutes,
            'percent' => $this->reportPercent,
            'reason' => $this->reportReason,
            'note' => $this->reportNote,
            'difficulty' => $this->reportDifficulty,
        ]), 'report', function (): void {
            $this->closeReport();
            session()->now('planner_status', __('habits.report.thanks'));
        });
    }

    public function quickDone(string $uuid, TransitionPlanOccurrence $transition): void
    {
        $occurrence = $this->reportable($uuid);
        $this->runPlannerAction(fn () => $transition->markDone($occurrence, $this->reportingUser()), 'execution.'.$occurrence->id);
    }

    /** One tap for the most common answer afterwards: it was done at the expected time. */
    public function quickDoneAsPlanned(string $uuid, TransitionPlanOccurrence $transition): void
    {
        $occurrence = $this->reportable($uuid);
        $this->runPlannerAction(fn () => $transition->report($occurrence, $this->reportingUser(), [
            'outcome' => 'done',
            'started_at' => $occurrence->scheduled_start_at,
            'minutes' => (int) $occurrence->scheduled_start_at->diffInMinutes($occurrence->scheduled_end_at),
        ]), 'execution.'.$occurrence->id);
    }

    public function quickStart(string $uuid, TransitionPlanOccurrence $transition): void
    {
        $occurrence = $this->reportable($uuid);
        $this->runPlannerAction(fn () => $transition->start($occurrence, $this->reportingUser()), 'execution.'.$occurrence->id);
    }

    public function quickSkip(string $uuid, TransitionPlanOccurrence $transition): void
    {
        $occurrence = $this->reportable($uuid);
        $this->runPlannerAction(fn () => $transition->skip($occurrence, $this->reportingUser()), 'execution.'.$occurrence->id);
    }

    public function quickCancel(string $uuid, TransitionPlanOccurrence $transition): void
    {
        $occurrence = $this->reportable($uuid);
        $this->runPlannerAction(fn () => $transition->cancel($occurrence, $this->reportingUser()), 'execution.'.$occurrence->id);
    }

    /** A refused action (wrong time, already handled) shows its reason instead of an error page. */
    private function runPlannerAction(callable $action, string $errorKey, ?callable $after = null): void
    {
        $this->resetErrorBag($errorKey);
        try {
            $action();
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() !== 422) {
                throw $exception;
            }
            $this->addError($errorKey, $exception->getMessage());

            return;
        }
        if ($after !== null) {
            $after();
        }
    }

    private function reportable(string $uuid): PlanOccurrence
    {
        $occurrence = PlanOccurrence::query()->with(['plan', 'scheduleRule'])->where('uuid', $uuid)->firstOrFail();
        abort_unless(Gate::forUser($this->reportingUser())->allows('participate', $occurrence->plan), 403);

        return $occurrence;
    }

    private function reportingUser(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    /** Whether the form for this occurrence uses a date too (open items may be handled on another day). */
    public function reportNeedsDate(PlanOccurrence $occurrence): bool
    {
        return OccurrenceTiming::policyOf($occurrence) === OccurrenceTiming::OPEN;
    }
}
