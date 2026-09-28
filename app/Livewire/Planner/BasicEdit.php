<?php

namespace App\Livewire\Planner;

use App\Actions\Planner\CancelPlanScheduleRule;
use App\Actions\Planner\CreatePlanScheduleRule;
use App\ContextKind;
use App\Models\Actor;
use App\Models\Plan;
use App\Models\PlanScheduleRule;
use App\Models\User;
use App\PlanOccurrenceStatus;
use App\PlanScheduleFrequency;
use App\PlanScheduleRuleStatus;
use App\PlanStatus;
use App\PlanTimingMode;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Edit planning item')]
class BasicEdit extends Component
{
    public Plan $plan;

    public string $title = '';

    public string $description = '';

    public string $category = '';

    public string $timingMode = 'fixed';

    public string $date = '';

    public string $startTime = '';

    public string $endTime = '';

    public function mount(Plan $plan): void
    {
        $user = $this->user();
        Gate::forUser($user)->authorize('manage', $plan);

        $plan->loadMissing('context');
        abort_unless(
            $plan->context->kind === ContextKind::Personal
            && data_get($plan->metadata, 'planning_studio') === 'baseline',
            404,
        );

        $rule = $this->activeRule($plan);
        abort_unless($rule->frequency === PlanScheduleFrequency::Once, 422);

        $this->plan = $plan;
        $this->title = $plan->title;
        $this->description = (string) $plan->description;
        $this->category = (string) data_get($plan->metadata, 'category', '');
        $this->timingMode = $rule->timing_mode->value;
        $this->date = $rule->starts_on->format('Y-m-d');

        if ($rule->timing_mode === PlanTimingMode::Fixed) {
            $this->startTime = substr($rule->start_time, 0, 5);
            $start = CarbonImmutable::parse($this->date.' '.$this->startTime, $rule->timezone);
            $this->endTime = $start->addMinutes($rule->duration_minutes)->format('H:i');
        } else {
            $this->startTime = '09:00';
            $this->endTime = '10:00';
        }
    }

    public function save(
        CancelPlanScheduleRule $cancelRule,
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
        $plan = Plan::query()->with('context')->findOrFail($this->plan->id);
        Gate::forUser($user)->authorize('manage', $plan);
        abort_unless($plan->status === PlanStatus::Active, 422);
        abort_if(
            $plan->occurrences()->where('status', PlanOccurrenceStatus::InProgress->value)->exists(),
            422,
            __('planning_baseline.validation.finish_running_before_edit'),
        );

        $timezone = $plan->timezone;
        $mode = PlanTimingMode::from($data['timingMode']);
        [$startTime, $duration] = $mode === PlanTimingMode::FlexibleDay
            ? ['00:00', 1440]
            : [$data['startTime'], $this->duration($data['date'], $data['startTime'], $data['endTime'], $timezone)];

        DB::transaction(function () use ($plan, $user, $data, $mode, $startTime, $duration, $cancelRule, $createRule): void {
            $metadata = $plan->metadata ?? [];
            $metadata['planning_studio'] = 'baseline';
            $metadata['category'] = trim($data['category']) !== '' ? trim($data['category']) : null;

            $plan->forceFill([
                'title' => trim($data['title']),
                'description' => trim($data['description']) !== '' ? trim($data['description']) : null,
                'metadata' => $metadata,
            ])->save();

            $rule = $this->activeRule($plan);
            $cancelRule->execute($rule, $user);

            $createRule->execute(
                $plan->fresh(),
                $user,
                PlanScheduleFrequency::Once,
                $data['date'],
                $startTime,
                $duration,
                timingMode: $mode,
            );
        }, attempts: 3);

        return $this->redirectRoute('planner.show', $plan);
    }

    public function render(): View
    {
        return view('livewire.planner.basic-edit');
    }

    private function activeRule(Plan $plan): PlanScheduleRule
    {
        return $plan->scheduleRules()
            ->where('status', PlanScheduleRuleStatus::Active->value)
            ->latest('id')
            ->firstOrFail();
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
