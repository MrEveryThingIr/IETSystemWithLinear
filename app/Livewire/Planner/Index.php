<?php

namespace App\Livewire\Planner;

use App\Models\Context;
use App\Models\Plan;
use App\Models\PlanOccurrence;
use App\Models\User;
use App\Support\TemporalPreferences;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    #[Url]
    public string $view = 'today';

    #[Url]
    public string $month = '';

    #[Url(as: 'context')]
    public string $contextUuid = '';

    #[Url(as: 'date')]
    public string $selectedDate = '';

    public function mount(): void
    {
        if (! in_array($this->view, ['today', 'list', 'calendar'], true)) {
            $this->view = 'today';
        }

        $timezone = TemporalPreferences::timezoneFor($this->user());
        $now = CarbonImmutable::now($timezone);

        if (! preg_match('/^\d{4}-\d{2}$/', $this->month)) {
            $this->month = $now->format('Y-m');
        }

        if ($this->view === 'calendar') {
            if (! $this->validDate($this->selectedDate, $timezone)) {
                $this->selectedDate = $now->format('Y-m-d');
            } else {
                $this->month = substr($this->selectedDate, 0, 7);
            }
        }
    }

    public function previousMonth(): void
    {
        $timezone = TemporalPreferences::timezoneFor($this->user());
        $this->month = CarbonImmutable::parse($this->month.'-01', $timezone)
            ->subMonth()
            ->format('Y-m');
    }

    public function nextMonth(): void
    {
        $timezone = TemporalPreferences::timezoneFor($this->user());
        $this->month = CarbonImmutable::parse($this->month.'-01', $timezone)
            ->addMonth()
            ->format('Y-m');
    }

    public function previousYear(): void
    {
        $timezone = TemporalPreferences::timezoneFor($this->user());
        $this->month = CarbonImmutable::parse($this->month.'-01', $timezone)
            ->subYear()
            ->format('Y-m');
    }

    public function nextYear(): void
    {
        $timezone = TemporalPreferences::timezoneFor($this->user());
        $this->month = CarbonImmutable::parse($this->month.'-01', $timezone)
            ->addYear()
            ->format('Y-m');
    }

    public function selectDate(string $date): void
    {
        $timezone = TemporalPreferences::timezoneFor($this->user());
        abort_unless($this->validDate($date, $timezone), 422);

        $this->selectedDate = $date;
        $this->month = substr($date, 0, 7);
    }

    public function focusSelectedDate(): void
    {
        $timezone = TemporalPreferences::timezoneFor($this->user());

        if (! $this->validDate($this->selectedDate, $timezone)) {
            $this->addError('selectedDate', __('planner.validation.calendar_date_invalid'));

            return;
        }

        $this->resetErrorBag('selectedDate');
        $this->month = substr($this->selectedDate, 0, 7);
    }

    public function render(): View
    {
        $user = $this->user();
        $timezone = TemporalPreferences::timezoneFor($user);
        $context = $this->context($user);

        [$from, $through] = $this->window($timezone);

        $occurrenceRelations = [
            'plan.context',
            'plan.participants.actor.user',
        ];

        if ($this->view === 'calendar') {
            $occurrenceRelations = [
                ...$occurrenceRelations,
                'assets',
                'evidenceReferences.content.activeRevision',
                'evidenceReferences.revision',
            ];
        }

        $occurrences = PlanOccurrence::query()
            ->with($occurrenceRelations)
            ->whereBetween('scheduled_start_at', [$from->utc(), $through->utc()])
            ->orderBy('scheduled_start_at')
            ->limit(500)
            ->get()
            ->filter(function (PlanOccurrence $occurrence) use ($user, $context): bool {
                return ($context === null || (int) $occurrence->plan->context_id === (int) $context->id)
                    && Gate::forUser($user)->allows('view', $occurrence->plan);
            })
            ->values();

        $plans = Plan::query()
            ->with(['context', 'participants.actor.user'])
            ->when($context instanceof Context, fn ($query) => $query->where('context_id', $context->id))
            ->latest('updated_at')
            ->limit(150)
            ->get()
            ->filter(fn (Plan $plan): bool => Gate::forUser($user)->allows('view', $plan))
            ->values();

        $calendarDays = collect();
        $calendarOccurrences = collect();

        if ($this->view === 'calendar') {
            $firstDay = TemporalPreferences::weekdayOrder($user->locale)[0] ?? 1;
            $carbonFirstDay = $firstDay === 7 ? CarbonInterface::SUNDAY : $firstDay;
            $month = CarbonImmutable::parse($this->month.'-01', $timezone);
            $gridStart = $month->startOfMonth()->startOfWeek($carbonFirstDay);
            $gridEnd = $month->endOfMonth()->endOfWeek($carbonFirstDay);

            for ($date = $gridStart; $date->lte($gridEnd); $date = $date->addDay()) {
                $calendarDays->push($date);
            }

            $calendarOccurrences = $occurrences->groupBy(
                fn (PlanOccurrence $occurrence): string => $occurrence->scheduled_start_at
                    ->setTimezone($timezone)
                    ->format('Y-m-d'),
            );
        }

        $selectedDayOccurrences = $this->view === 'calendar' && $this->validDate($this->selectedDate, $timezone)
            ? $calendarOccurrences->get($this->selectedDate, collect())
            : collect();

        $today = CarbonImmutable::now($timezone)->format('Y-m-d');
        $selectedTemporalState = $this->selectedDate === ''
            ? null
            : ($this->selectedDate === $today
                ? 'today'
                : ($this->selectedDate < $today ? 'past' : 'future'));

        return view('livewire.planner.index', [
            'plans' => $plans,
            'occurrences' => $occurrences,
            'calendarDays' => $calendarDays,
            'calendarOccurrences' => $calendarOccurrences,
            'selectedDayOccurrences' => $selectedDayOccurrences,
            'selectedTemporalState' => $selectedTemporalState,
            'timezone' => $timezone,
            'context' => $context,
        ])->title(__('planner.title'));
    }

    /** @return array{CarbonImmutable, CarbonImmutable} */
    private function window(string $timezone): array
    {
        $now = CarbonImmutable::now($timezone);

        if ($this->view === 'today') {
            return [$now->startOfDay(), $now->endOfDay()];
        }

        if ($this->view === 'list') {
            return [$now->startOfDay(), $now->addDays(30)->endOfDay()];
        }

        $firstDay = TemporalPreferences::weekdayOrder($this->user()->locale)[0] ?? 1;
        $carbonFirstDay = $firstDay === 7 ? CarbonInterface::SUNDAY : $firstDay;
        $month = CarbonImmutable::parse($this->month.'-01', $timezone);

        return [
            $month->startOfMonth()->startOfWeek($carbonFirstDay),
            $month->endOfMonth()->endOfWeek($carbonFirstDay),
        ];
    }

    private function validDate(string $date, string $timezone): bool
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            return false;
        }

        try {
            $parsed = CarbonImmutable::createFromFormat('!Y-m-d', $date, $timezone);
        } catch (\Throwable) {
            return false;
        }

        return $parsed instanceof CarbonImmutable && $parsed->format('Y-m-d') === $date;
    }

    private function context(User $user): ?Context
    {
        if ($this->contextUuid === '') {
            return null;
        }

        $context = Context::query()->where('uuid', $this->contextUuid)->firstOrFail();
        Gate::forUser($user)->authorize('view', $context);

        return $context;
    }

    private function user(): User
    {
        $user = request()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
