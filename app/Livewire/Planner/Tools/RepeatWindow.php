<?php

namespace App\Livewire\Planner\Tools;

use App\Actions\Planner\ReplicateOccurrenceToDates;
use App\Models\Actor;
use App\Models\PlanOccurrence;
use App\Models\User;
use App\PlanOccurrenceStatus;
use App\PlanScheduleFrequency;
use App\PlanTimingMode;
use App\Support\TemporalPreferences;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

#[Layout('layouts.app')]
#[Title('Repeat time window')]
class RepeatWindow extends Component
{
    #[Url(as: 'source')]
    public string $sourceUuid = '';

    public string $repeatMode = 'next_days';

    public int $repeatCount = 21;

    public string $repeatMonth = '';

    public string $repeatDate = '';

    /** @var list<string> */
    public array $repeatDates = [];

    public string $message = '';

    public function mount(): void
    {
        abort_unless((string) config('release.profile') === 'planning_baseline', 404);

        $timezone = TemporalPreferences::timezoneFor($this->user());
        $this->repeatMonth = CarbonImmutable::now($timezone)->addMonth()->format('Y-m');
    }

    public function updatedSourceUuid(): void
    {
        $this->message = '';
        $this->resetValidation();
    }

    public function addRepeatDate(): void
    {
        $data = $this->validate([
            'repeatDate' => ['required', 'date_format:Y-m-d'],
        ]);

        if (! in_array($data['repeatDate'], $this->repeatDates, true)) {
            $this->repeatDates[] = $data['repeatDate'];
            sort($this->repeatDates);
        }

        $this->repeatDate = '';
        $this->resetValidation('repeatDate');
    }

    public function removeRepeatDate(string $date): void
    {
        $this->repeatDates = array_values(array_filter(
            $this->repeatDates,
            fn (string $candidate): bool => $candidate !== $date,
        ));
    }

    public function apply(ReplicateOccurrenceToDates $repeat): void
    {
        $source = $this->sourceOccurrence();
        $dates = $this->targetDates($source);
        $copies = $repeat->execute($source, $this->user(), $dates);

        $this->message = trans_choice(
            'planning_baseline.tools.repeat.created',
            $copies->count(),
            ['count' => $copies->count()],
        );
        $this->repeatDates = [];
        $this->repeatDate = '';
        $this->resetValidation();
    }

    public function render(): View
    {
        $sources = $this->availableSources();
        $source = $this->sourceUuid !== ''
            ? $sources->firstWhere('uuid', $this->sourceUuid)
            : null;

        return view('livewire.planner.tools.repeat-window', [
            'sources' => $sources,
            'sourceOccurrence' => $source,
        ]);
    }

    private function sourceOccurrence(): PlanOccurrence
    {
        if ($this->sourceUuid === '') {
            throw ValidationException::withMessages([
                'sourceUuid' => __('planning_baseline.tools.repeat.choose_source'),
            ]);
        }

        $source = $this->availableSources()->firstWhere('uuid', $this->sourceUuid);

        if (! $source instanceof PlanOccurrence) {
            throw ValidationException::withMessages([
                'sourceUuid' => __('planning_baseline.tools.repeat.choose_source'),
            ]);
        }

        Gate::forUser($this->user())->authorize('manage', $source->plan);

        return $source;
    }

    /**
     * @return Collection<int, PlanOccurrence>
     */
    private function availableSources(): Collection
    {
        $user = $this->user();

        return PlanOccurrence::query()
            ->with(['plan', 'scheduleRule'])
            ->whereHas('plan', function ($query) use ($user): void {
                $query
                    ->where('created_by_actor_id', $user->actor->id)
                    ->where('metadata->planning_studio', 'baseline');
            })
            ->whereHas('scheduleRule', fn ($query) => $query->where('frequency', PlanScheduleFrequency::Once->value))
            ->where('status', '!=', PlanOccurrenceStatus::Cancelled->value)
            ->latest('scheduled_start_at')
            ->limit(200)
            ->get();
    }

    /**
     * @return list<string>
     */
    private function targetDates(PlanOccurrence $source): array
    {
        $timezone = $source->plan->timezone;
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $sourceDay = CarbonImmutable::parse($source->local_date->format('Y-m-d'), $timezone)->startOfDay();
        $anchor = $sourceDay->greaterThan($today) ? $sourceDay : $today;

        if ($this->repeatMode === 'next_days') {
            $this->validate(['repeatCount' => ['required', 'integer', 'min:1', 'max:62']]);

            return collect(range(1, $this->repeatCount))
                ->map(fn (int $offset): string => $anchor->addDays($offset)->format('Y-m-d'))
                ->all();
        }

        if ($this->repeatMode === 'same_weekday') {
            $this->validate(['repeatCount' => ['required', 'integer', 'min:1', 'max:52']]);

            $weekday = $sourceDay->isoWeekday();
            $cursor = $anchor;
            $dates = [];

            while (count($dates) < $this->repeatCount) {
                $cursor = $cursor->addDay();

                if ($cursor->isoWeekday() === $weekday) {
                    $dates[] = $cursor->format('Y-m-d');
                }
            }

            return $dates;
        }

        if ($this->repeatMode === 'same_monthday') {
            $this->validate(['repeatCount' => ['required', 'integer', 'min:1', 'max:36']]);

            $day = $sourceDay->day;
            $cursor = $anchor->startOfMonth();
            $dates = [];
            $sourceDate = $sourceDay->format('Y-m-d');

            while (count($dates) < $this->repeatCount) {
                if ($day <= $cursor->daysInMonth) {
                    $candidate = $cursor->day($day);

                    if (
                        $candidate->greaterThan($anchor)
                        && $candidate->greaterThan($today)
                        && $candidate->format('Y-m-d') !== $sourceDate
                    ) {
                        $dates[] = $candidate->format('Y-m-d');
                    }
                }

                $cursor = $cursor->addMonth()->startOfMonth();
            }

            return $dates;
        }

        if ($this->repeatMode === 'same_yearday') {
            $this->validate(['repeatCount' => ['required', 'integer', 'min:1', 'max:20']]);

            $month = $sourceDay->month;
            $day = $sourceDay->day;
            $year = $anchor->year;
            $dates = [];
            $sourceDate = $sourceDay->format('Y-m-d');

            while (count($dates) < $this->repeatCount) {
                try {
                    $candidate = CarbonImmutable::createSafe($year, $month, $day, 0, 0, 0, $timezone);
                } catch (Throwable) {
                    $year++;

                    continue;
                }

                if (
                    $candidate->greaterThan($anchor)
                    && $candidate->greaterThan($today)
                    && $candidate->format('Y-m-d') !== $sourceDate
                ) {
                    $dates[] = $candidate->format('Y-m-d');
                }

                $year++;
            }

            return $dates;
        }

        if ($this->repeatMode === 'month') {
            $this->validate(['repeatMonth' => ['required', 'date_format:Y-m']]);

            $start = CarbonImmutable::parse($this->repeatMonth.'-01', $timezone)->startOfDay();
            $end = $start->endOfMonth();
            $sourceDate = $sourceDay->format('Y-m-d');
            $dates = [];

            for ($cursor = $start; $cursor->lte($end); $cursor = $cursor->addDay()) {
                if ($cursor->greaterThan($today) && $cursor->format('Y-m-d') !== $sourceDate) {
                    $dates[] = $cursor->format('Y-m-d');
                }
            }

            if ($dates === []) {
                throw ValidationException::withMessages([
                    'repeatMonth' => __('planning_baseline.tools.repeat.future_month_required'),
                ]);
            }

            return $dates;
        }

        if ($this->repeatMode === 'selected_dates') {
            $this->validate([
                'repeatDates' => ['required', 'array', 'min:1', 'max:62'],
                'repeatDates.*' => ['required', 'date_format:Y-m-d'],
            ], [
                'repeatDates.required' => __('planning_baseline.tools.repeat.choose_dates'),
                'repeatDates.min' => __('planning_baseline.tools.repeat.choose_dates'),
            ]);

            $sourceDate = $sourceDay->format('Y-m-d');

            foreach ($this->repeatDates as $date) {
                $candidate = CarbonImmutable::parse($date, $timezone)->startOfDay();

                if (! $candidate->greaterThan($today)) {
                    throw ValidationException::withMessages([
                        'repeatDate' => __('planning_baseline.tools.repeat.future_only'),
                    ]);
                }

                if ($date === $sourceDate) {
                    throw ValidationException::withMessages([
                        'repeatDate' => __('planning_baseline.tools.repeat.other_instances_only'),
                    ]);
                }
            }

            return $this->repeatDates;
        }

        throw ValidationException::withMessages([
            'repeatMode' => __('planning_baseline.tools.repeat.invalid_mode'),
        ]);
    }

    private function user(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User && $user->actor instanceof Actor, 403);

        return $user;
    }
}
