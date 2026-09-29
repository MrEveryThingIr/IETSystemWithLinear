<?php

namespace App\Livewire\Planner;

use App\Actions\Contexts\EnsurePersonalContext;
use App\Actions\Planner\CreatePlan;
use App\Actions\Planner\CreatePlanScheduleRule;
use App\Models\Actor;
use App\Models\User;
use App\PlanAttentionMode;
use App\PlanScheduleFrequency;
use App\PlanTimingMode;
use App\Support\PlanAttentionConflicts;
use App\Support\TemporalPreferences;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('New planning item')]
class BasicCreate extends Component
{
    public string $title = '';

    public string $description = '';

    public string $category = '';

    public string $timingMode = 'fixed';

    public string $attentionMode = 'exclusive';

    public string $date = '';

    public string $startTime = '';

    public string $endTime = '';

    public function mount(): void
    {
        $user = $this->user();
        $timezone = TemporalPreferences::timezoneFor($user);
        $now = CarbonImmutable::now($timezone);
        $defaultStart = $now->addHour()->startOfHour();

        $requestedDate = trim((string) request()->query('date', ''));
        $this->date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $requestedDate) === 1
            ? $requestedDate
            : $defaultStart->toDateString();

        $requestedMode = trim((string) request()->query('timing', ''));
        if (in_array($requestedMode, array_column(PlanTimingMode::cases(), 'value'), true)) {
            $this->timingMode = $requestedMode;
        }

        $requestedTime = trim((string) request()->query('time', ''));
        $this->startTime = preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $requestedTime) === 1
            ? $requestedTime
            : $defaultStart->format('H:i');

        $requestedDuration = (int) request()->query('duration', 60);
        $requestedDuration = max(1, min(720, $requestedDuration));

        $start = CarbonImmutable::parse($this->date.' '.$this->startTime, $timezone);
        $this->endTime = $start->addMinutes($requestedDuration)->format('H:i');
    }

    public function save(
        EnsurePersonalContext $personal,
        CreatePlan $createPlan,
        CreatePlanScheduleRule $createRule,
        PlanAttentionConflicts $attentionConflicts,
    ): mixed {
        $data = $this->validate([
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:10000'],
            'category' => ['nullable', 'string', 'max:80'],
            'timingMode' => ['required', 'in:fixed,flexible_day'],
            'attentionMode' => ['required', 'in:exclusive,background'],
            'date' => ['required', 'date_format:Y-m-d'],
            'startTime' => ['required_if:timingMode,fixed', 'date_format:H:i'],
            'endTime' => ['required_if:timingMode,fixed', 'date_format:H:i'],
        ]);

        $user = $this->user();
        $timezone = TemporalPreferences::timezoneFor($user);
        $mode = PlanTimingMode::from($data['timingMode']);
        $attentionMode = PlanAttentionMode::from($data['attentionMode']);

        [$startTime, $duration] = $mode === PlanTimingMode::FlexibleDay
            ? ['00:00', 1440]
            : [$data['startTime'], $this->duration($data['date'], $data['startTime'], $data['endTime'], $timezone)];

        $category = trim($data['category']);
        $description = trim($data['description']);
        $context = $personal->execute($user);

        if ($mode === PlanTimingMode::Fixed && $attentionMode === PlanAttentionMode::Exclusive) {
            $start = CarbonImmutable::parse($data['date'].' '.$startTime, $timezone);
            $end = $start->addMinutes($duration);
            $conflict = $attentionConflicts->firstForWindow($context, $start, $end);

            if ($conflict !== null) {
                throw ValidationException::withMessages([
                    'startTime' => __('planning_baseline.validation.exclusive_overlap', [
                        'title' => $conflict->plan->title,
                    ]),
                ]);
            }
        }

        $plan = DB::transaction(function () use (
            $createPlan,
            $createRule,
            $context,
            $user,
            $data,
            $description,
            $timezone,
            $category,
            $attentionMode,
            $startTime,
            $duration,
            $mode,
        ) {
            $plan = $createPlan->execute(
                $context,
                $user,
                $data['title'],
                $description !== '' ? $description : null,
                $timezone,
                metadata: [
                    'planning_studio' => 'baseline',
                    'category' => $category !== '' ? $category : null,
                ],
                attentionMode: $attentionMode,
            );

            $createRule->execute(
                $plan,
                $user,
                PlanScheduleFrequency::Once,
                $data['date'],
                $startTime,
                $duration,
                timingMode: $mode,
            );

            return $plan;
        }, attempts: 3);

        return $this->redirectRoute('planner.show', $plan);
    }

    public function render(): View
    {
        return view('livewire.planner.basic-create');
    }

    private function duration(string $date, string $startTime, string $endTime, string $timezone): int
    {
        $start = CarbonImmutable::parse($date.' '.$startTime, $timezone);
        $end = CarbonImmutable::parse($date.' '.$endTime, $timezone);

        if (! $end->greaterThan($start)) {
            throw ValidationException::withMessages([
                'endTime' => __('planning_baseline.validation.end_after_start'),
            ]);
        }

        return (int) $start->diffInMinutes($end);
    }

    private function user(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User && $user->actor instanceof Actor, 403);

        return $user;
    }
}
