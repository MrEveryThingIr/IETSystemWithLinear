<?php

namespace App\Livewire\Planner\Tools;

use App\Actions\Planner\ReplicateOccurrenceToDates;
use App\Models\Actor;
use App\Models\PlanOccurrence;
use App\Models\User;
use App\PlanOccurrenceStatus;
use App\PlanScheduleFrequency;
use App\Support\TemporalCalendar;
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

        $user = $this->user();
        $timezone = TemporalPreferences::timezoneFor($user);
        $currentMonth = TemporalCalendar::monthStart(CarbonImmutable::now($timezone), $user, $timezone);
        $this->repeatMonth = TemporalCalendar::nextMonthStart($currentMonth, $user, $timezone)->format('Y-m-d');
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

        $cancelUrl = $source instanceof PlanOccurrence
            ? route('planner.show', $source->plan)
            : route('planner.index');

        $user = $this->user();
        $sourceLabels = $sources->mapWithKeys(function (PlanOccurrence $occurrence) use ($user): array {
            $timezone = $occurrence->plan->timezone;
            $date = CarbonImmutable::parse($occurrence->local_date->format('Y-m-d'), 'UTC');
            $dateLabel = TemporalCalendar::dateLabel($date, $user, 'UTC');

            if ($occurrence->scheduleRule?->timing_mode?->value === 'flexible_day') {
                $timeLabel = __('planning_baseline.timing.flexible_day_short');
            } else {
                $localStart = CarbonImmutable::parse(
                    $occurrence->local_date->format('Y-m-d').' '.$occurrence->scheduleRule->start_time,
                    $timezone,
                );
                $timeLabel = TemporalCalendar::timeLabel($localStart, $user, $timezone);
            }

            return [$occurrence->uuid => $occurrence->plan->title.' — '.$dateLabel.' · '.$timeLabel];
        });

        return view('livewire.planner.tools.repeat-window', [
            'sources' => $sources,
            'sourceOccurrence' => $source,
            'sourceLabels' => $sourceLabels,
            'cancelUrl' => $cancelUrl,
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

        if (! ($source instanceof PlanOccurrence)) {
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

            $user = $this->user();
            $day = (int) TemporalCalendar::format($sourceDay, $user, $timezone, 'd', 'en');
            $cursor = TemporalCalendar::monthStart($anchor, $user, $timezone);
            $dates = [];
            $sourceDate = $sourceDay->format('Y-m-d');

            while (count($dates) < $this->repeatCount) {
                $candidate = $cursor->addDays(max(0, $day - 1));
                $candidateDay = (int) TemporalCalendar::format($candidate, $user, $timezone, 'd', 'en');

                if (
                    $candidateDay === $day
                    && TemporalCalendar::monthKey($candidate, $user, $timezone) === TemporalCalendar::monthKey($cursor, $user, $timezone)
                    && $candidate->greaterThan($anchor)
                    && $candidate->greaterThan($today)
                    && $candidate->format('Y-m-d') !== $sourceDate
                ) {
                    $dates[] = $candidate->format('Y-m-d');
                }

                $cursor = TemporalCalendar::nextMonthStart($cursor, $user, $timezone);
            }

            return $dates;
        }

        if ($this->repeatMode === 'same_yearday') {
            $this->validate(['repeatCount' => ['required', 'integer', 'min:1', 'max:20']]);

            $user = $this->user();
            $month = (int) TemporalCalendar::format($sourceDay, $user, $timezone, 'M', 'en');
            $day = (int) TemporalCalendar::format($sourceDay, $user, $timezone, 'd', 'en');
            $yearCursor = TemporalCalendar::yearStart($anchor, $user, $timezone);
            $dates = [];
            $sourceDate = $sourceDay->format('Y-m-d');

            while (count($dates) < $this->repeatCount) {
                $monthCursor = $yearCursor;

                for ($index = 1; $index < $month; $index++) {
                    $monthCursor = TemporalCalendar::nextMonthStart($monthCursor, $user, $timezone);
                }

                $candidate = $monthCursor->addDays(max(0, $day - 1));
                $candidateMonth = (int) TemporalCalendar::format($candidate, $user, $timezone, 'M', 'en');
                $candidateDay = (int) TemporalCalendar::format($candidate, $user, $timezone, 'd', 'en');

                if (
                    $candidateMonth === $month
                    && $candidateDay === $day
                    && $candidate->greaterThan($anchor)
                    && $candidate->greaterThan($today)
                    && $candidate->format('Y-m-d') !== $sourceDate
                ) {
                    $dates[] = $candidate->format('Y-m-d');
                }

                $yearCursor = TemporalCalendar::nextYearStart($yearCursor, $user, $timezone);
            }

            return $dates;
        }

        if ($this->repeatMode === 'month') {
            $this->validate(['repeatMonth' => ['required', 'date_format:Y-m-d']]);

            $user = $this->user();
            $picked = CarbonImmutable::parse($this->repeatMonth, $timezone)->startOfDay();
            $start = TemporalCalendar::monthStart($picked, $user, $timezone);
            $end = TemporalCalendar::nextMonthStart($start, $user, $timezone)->subDay();
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
