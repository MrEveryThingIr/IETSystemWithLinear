<?php

namespace App\Livewire\Planner;

use App\Actions\Contexts\EnsurePersonalContext;
use App\Actions\Planner\CreatePlan;
use App\Actions\Planner\CreatePlanScheduleRule;
use App\Models\Actor;
use App\Models\User;
use App\PlanScheduleFrequency;
use App\PlanTimingMode;
use App\Support\TemporalPreferences;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('New planning item')]
class Create extends Component
{
    public string $title = '';

    public string $description = '';

    public string $category = '';

    public string $timingMode = 'fixed';

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
    ): mixed {
        $data = $this->validate([
            'title' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:10000'],
            'category' => ['nullable', 'string', 'max:80'],
            'timingMode' => ['required', 'in:fixed,flexible_day'],
            'date' => ['required', 'date_format:Y-m-d'],
            'startTime' => ['required_if:timingMode,fixed', 'date_format:H:i'],
            'endTime' => ['required_if:timingMode,fixed', 'date_format:H:i'],
        ]);

        $user = $this->user();
        $timezone = TemporalPreferences::timezoneFor($user);
        $mode = PlanTimingMode::from($data['timingMode']);

        [$startTime, $duration] = $mode === PlanTimingMode::FlexibleDay
            ? ['00:00', 1440]
            : [$data['startTime'], $this->duration($data['date'], $data['startTime'], $data['endTime'], $timezone)];

        $category = trim($data['category']);
        $description = trim($data['description']);

        $plan = $createPlan->execute(
            $personal->execute($user),
            $user,
            $data['title'],
            $description !== '' ? $description : null,
            $timezone,
            metadata: [
                'planning_studio' => 'baseline',
                'category' => $category !== '' ? $category : null,
            ],
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

        return $this->redirectRoute('planner.show', $plan);
    }

    public function render(): View
    {
        return view('livewire.planner.create');
    }

    private function duration(string $date, string $startTime, string $endTime, string $timezone): int
    {
        $start = CarbonImmutable::parse($date.' '.$startTime, $timezone);
        $end = CarbonImmutable::parse($date.' '.$endTime, $timezone);

        abort_unless($end->greaterThan($start), 422, __('planning_baseline.validation.end_after_start'));

        return (int) $start->diffInMinutes($end);
    }

    private function user(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User && $user->actor instanceof Actor, 403);

        return $user;
    }
}
