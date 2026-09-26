<?php

namespace App\Livewire\Planner;

use App\Models\Context;
use App\Models\Plan;
use App\Models\PlanOccurrence;
use App\Models\User;
use App\Support\TemporalCalendar;
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

    /**
     * Canonical Gregorian date identifying the first day of the selected
     * profile-calendar month.
     */
    #[Url]
    public string $month = '';

    #[Url(as: 'level')]
    public string $calendarLevel = 'month';

    /**
     * Canonical Gregorian date identifying the first day of the selected
     * profile-calendar year.
     */
    #[Url]
    public string $year = '';

    /** Canonical Gregorian local date for the selected civil day. */
    #[Url]
    public string $day = '';

    #[Url]
    public int $hour = 0;

    #[Url(as: 'quantum')]
    public int $slotMinutes = 15;

    #[Url(as: 'context')]
    public string $contextUuid = '';

    public function mount(): void
    {
        if (! in_array($this->view, ['today', 'list', 'calendar'], true)) {
            $this->view = 'today';
        }

        if (! in_array($this->calendarLevel, ['year', 'month', 'day', 'hour'], true)) {
            $this->calendarLevel = 'month';
        }

        $user = $this->user();
        $timezone = TemporalPreferences::timezoneFor($user);
        $now = CarbonImmutable::now($timezone);

        $monthAnchor = $this->parseDateAnchor($this->month, $timezone) ?? $now;
        $monthStart = TemporalCalendar::monthStart($monthAnchor, $user, $timezone);
        $this->month = $monthStart->toDateString();

        $yearAnchor = $this->parseDateAnchor($this->year, $timezone) ?? $monthStart;
        $this->year = TemporalCalendar::yearStart($yearAnchor, $user, $timezone)->toDateString();

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->day)) {
            $this->day = $now->toDateString();
        }

        $this->hour = min(23, max(0, $this->hour));

        if (! in_array($this->slotMinutes, [60, 30, 15, 5, 1], true)) {
            $this->slotMinutes = 15;
        }
    }

    public function previousMonth(): void
    {
        $user = $this->user();
        $timezone = TemporalPreferences::timezoneFor($user);
        $start = TemporalCalendar::previousMonthStart(
            CarbonImmutable::parse($this->month, $timezone),
            $user,
            $timezone,
        );

        $this->month = $start->toDateString();
        $this->year = TemporalCalendar::yearStart($start, $user, $timezone)->toDateString();
    }

    public function nextMonth(): void
    {
        $user = $this->user();
        $timezone = TemporalPreferences::timezoneFor($user);
        $start = TemporalCalendar::nextMonthStart(
            CarbonImmutable::parse($this->month, $timezone),
            $user,
            $timezone,
        );

        $this->month = $start->toDateString();
        $this->year = TemporalCalendar::yearStart($start, $user, $timezone)->toDateString();
    }

    public function previousPeriod(): void
    {
        $user = $this->user();
        $timezone = TemporalPreferences::timezoneFor($user);

        if ($this->calendarLevel === 'year') {
            $start = TemporalCalendar::previousYearStart(
                CarbonImmutable::parse($this->year, $timezone),
                $user,
                $timezone,
            );
            $this->year = $start->toDateString();
            $this->month = $start->toDateString();

            return;
        }

        if ($this->calendarLevel === 'hour') {
            $date = CarbonImmutable::parse($this->day, $timezone)
                ->setTime($this->hour, 0)
                ->subHour();
            $this->showHour($date->toDateString(), $date->hour);

            return;
        }

        if ($this->calendarLevel === 'day') {
            $this->showDay(CarbonImmutable::parse($this->day, $timezone)->subDay()->toDateString());

            return;
        }

        $this->previousMonth();
    }

    public function nextPeriod(): void
    {
        $user = $this->user();
        $timezone = TemporalPreferences::timezoneFor($user);

        if ($this->calendarLevel === 'year') {
            $start = TemporalCalendar::nextYearStart(
                CarbonImmutable::parse($this->year, $timezone),
                $user,
                $timezone,
            );
            $this->year = $start->toDateString();
            $this->month = $start->toDateString();

            return;
        }

        if ($this->calendarLevel === 'hour') {
            $date = CarbonImmutable::parse($this->day, $timezone)
                ->setTime($this->hour, 0)
                ->addHour();
            $this->showHour($date->toDateString(), $date->hour);

            return;
        }

        if ($this->calendarLevel === 'day') {
            $this->showDay(CarbonImmutable::parse($this->day, $timezone)->addDay()->toDateString());

            return;
        }

        $this->nextMonth();
    }

    public function showYear(?string $anchor = null): void
    {
        $user = $this->user();
        $timezone = TemporalPreferences::timezoneFor($user);
        $date = $this->parseDateAnchor($anchor ?? $this->month, $timezone)
            ?? CarbonImmutable::now($timezone);

        $start = TemporalCalendar::yearStart($date, $user, $timezone);
        $this->year = $start->toDateString();
        $this->month = $start->toDateString();
        $this->calendarLevel = 'year';
    }

    public function showMonth(string $anchor): void
    {
        $user = $this->user();
        $timezone = TemporalPreferences::timezoneFor($user);
        $date = $this->parseDateAnchor($anchor, $timezone);
        abort_unless($date instanceof CarbonImmutable, 422);

        $start = TemporalCalendar::monthStart($date, $user, $timezone);
        $this->month = $start->toDateString();
        $this->year = TemporalCalendar::yearStart($start, $user, $timezone)->toDateString();
        $this->calendarLevel = 'month';
    }

    public function showDay(string $day): void
    {
        abort_unless(preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) === 1, 422);

        $user = $this->user();
        $timezone = TemporalPreferences::timezoneFor($user);
        $date = CarbonImmutable::parse($day, $timezone);

        $this->day = $day;
        $this->month = TemporalCalendar::monthStart($date, $user, $timezone)->toDateString();
        $this->year = TemporalCalendar::yearStart($date, $user, $timezone)->toDateString();
        $this->calendarLevel = 'day';
    }

    public function showHour(string $day, int $hour): void
    {
        abort_unless(preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) === 1, 422);
        abort_unless($hour >= 0 && $hour <= 23, 422);

        $user = $this->user();
        $timezone = TemporalPreferences::timezoneFor($user);
        $date = CarbonImmutable::parse($day, $timezone);

        $this->day = $day;
        $this->month = TemporalCalendar::monthStart($date, $user, $timezone)->toDateString();
        $this->year = TemporalCalendar::yearStart($date, $user, $timezone)->toDateString();
        $this->hour = $hour;
        $this->calendarLevel = 'hour';
    }

    public function setSlotMinutes(int $minutes): void
    {
        abort_unless(in_array($minutes, [60, 30, 15, 5, 1], true), 422);

        $this->slotMinutes = $minutes;
    }

    public function render(): View
    {
        $user = $this->user();
        $timezone = TemporalPreferences::timezoneFor($user);
        $context = $this->context($user);

        [$from, $through] = $this->window($timezone);

        $occurrences = PlanOccurrence::query()
            ->with([
                'plan.context',
                'plan.participants.actor.user',
            ])
            ->when($context instanceof Context, fn ($query) => $query->whereHas(
                'plan',
                fn ($plans) => $plans->where('context_id', $context->id),
            ))
            ->whereBetween('scheduled_start_at', [$from->utc(), $through->utc()])
            ->orderBy('scheduled_start_at')
            ->get()
            ->filter(function (PlanOccurrence $occurrence) use ($user, $context): bool {
                return ($context === null || (int) $occurrence->plan->context_id === (int) $context->id)
                    && Gate::forUser($user)->allows('view', $occurrence->plan);
            })
            ->take(500)
            ->values();

        $plans = Plan::query()
            ->with(['context', 'participants.actor.user'])
            ->when($context instanceof Context, fn ($query) => $query->where('context_id', $context->id))
            ->latest('updated_at')
            ->get()
            ->filter(fn (Plan $plan): bool => Gate::forUser($user)->allows('view', $plan))
            ->take(150)
            ->values();

        $calendarDays = collect();
        $calendarOccurrences = collect();
        $calendarMonths = collect();
        $calendarHours = collect();
        $calendarSlots = collect();

        $monthStart = CarbonImmutable::parse($this->month, $timezone);
        $yearStart = CarbonImmutable::parse($this->year, $timezone);
        $selectedMonthKey = TemporalCalendar::monthKey($monthStart, $user, $timezone);

        if ($this->view === 'calendar') {
            if ($this->calendarLevel === 'year') {
                $cursor = $yearStart;
                for ($index = 0; $index < 12; $index++) {
                    $next = TemporalCalendar::nextMonthStart($cursor, $user, $timezone);
                    $calendarMonths->push([
                        'date' => $cursor,
                        'key' => $cursor->toDateString(),
                        'label' => TemporalCalendar::monthLabel($cursor, $user, $timezone),
                        'count' => $occurrences->filter(function (PlanOccurrence $occurrence) use ($cursor, $next, $timezone): bool {
                            $start = $occurrence->scheduled_start_at->setTimezone($timezone);

                            return $start->gte($cursor) && $start->lt($next);
                        })->count(),
                    ]);
                    $cursor = $next;
                }
            } elseif ($this->calendarLevel === 'hour') {
                $hourStart = CarbonImmutable::parse($this->day, $timezone)->setTime($this->hour, 0);

                for ($minute = 0; $minute < 60; $minute += $this->slotMinutes) {
                    $slotStart = $hourStart->setTime($this->hour, $minute);
                    $slotEnd = $slotStart->addMinutes($this->slotMinutes);

                    $calendarSlots->push([
                        'start' => $slotStart,
                        'end' => $slotEnd,
                        'items' => $occurrences->filter(function (PlanOccurrence $occurrence) use ($slotStart, $slotEnd, $timezone): bool {
                            $start = $occurrence->scheduled_start_at->setTimezone($timezone);

                            return $start->gte($slotStart) && $start->lt($slotEnd);
                        })->values(),
                    ]);
                }
            } elseif ($this->calendarLevel === 'day') {
                $calendarHours = $occurrences->groupBy(
                    fn (PlanOccurrence $occurrence): int => (int) $occurrence->scheduled_start_at
                        ->setTimezone($timezone)
                        ->format('G'),
                );
            } else {
                $firstDay = TemporalPreferences::weekdayOrder($user->locale)[0] ?? 1;
                $carbonFirstDay = $firstDay === 7 ? CarbonInterface::SUNDAY : $firstDay;
                $monthEnd = TemporalCalendar::nextMonthStart($monthStart, $user, $timezone)->subDay();
                $gridStart = $monthStart->startOfWeek($carbonFirstDay);
                $gridEnd = $monthEnd->endOfWeek($carbonFirstDay);

                for ($date = $gridStart; $date->lte($gridEnd); $date = $date->addDay()) {
                    $calendarDays->push([
                        'date' => $date,
                        'key' => $date->toDateString(),
                        'label' => TemporalCalendar::dayLabel($date, $user, $timezone),
                        'in_month' => TemporalCalendar::monthKey($date, $user, $timezone) === $selectedMonthKey,
                    ]);
                }

                $calendarOccurrences = $occurrences->groupBy(
                    fn (PlanOccurrence $occurrence): string => $occurrence->scheduled_start_at
                        ->setTimezone($timezone)
                        ->format('Y-m-d'),
                );
            }
        }

        return view('livewire.planner.index', [
            'plans' => $plans,
            'occurrences' => $occurrences,
            'calendarDays' => $calendarDays,
            'calendarOccurrences' => $calendarOccurrences,
            'calendarMonths' => $calendarMonths,
            'calendarHours' => $calendarHours,
            'calendarSlots' => $calendarSlots,
            'calendarYearLabel' => TemporalCalendar::yearLabel($yearStart, $user, $timezone),
            'calendarMonthLabel' => TemporalCalendar::monthLabel($monthStart, $user, $timezone),
            'timezone' => $timezone,
            'context' => $context,
        ])->title(__('planner.title'));
    }

    /** @return array{CarbonImmutable, CarbonImmutable} */
    private function window(string $timezone): array
    {
        $user = $this->user();
        $now = CarbonImmutable::now($timezone);

        if ($this->view === 'today') {
            return [$now->startOfDay(), $now->endOfDay()];
        }

        if ($this->view === 'list') {
            return [$now->startOfDay(), $now->addDays(30)->endOfDay()];
        }

        if ($this->calendarLevel === 'year') {
            $start = CarbonImmutable::parse($this->year, $timezone);
            $next = TemporalCalendar::nextYearStart($start, $user, $timezone);

            return [$start, $next->subMicrosecond()];
        }

        if ($this->calendarLevel === 'hour') {
            $hour = CarbonImmutable::parse($this->day, $timezone)->setTime($this->hour, 0);

            return [$hour->startOfHour(), $hour->endOfHour()];
        }

        if ($this->calendarLevel === 'day') {
            $day = CarbonImmutable::parse($this->day, $timezone);

            return [$day->startOfDay(), $day->endOfDay()];
        }

        $firstDay = TemporalPreferences::weekdayOrder($user->locale)[0] ?? 1;
        $carbonFirstDay = $firstDay === 7 ? CarbonInterface::SUNDAY : $firstDay;
        $month = CarbonImmutable::parse($this->month, $timezone);
        $monthEnd = TemporalCalendar::nextMonthStart($month, $user, $timezone)->subDay();

        return [
            $month->startOfWeek($carbonFirstDay),
            $monthEnd->endOfWeek($carbonFirstDay),
        ];
    }

    private function parseDateAnchor(?string $value, string $timezone): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (preg_match('/^\d{4}$/', $value) === 1) {
            return CarbonImmutable::create((int) $value, 1, 1, 0, 0, 0, $timezone);
        }

        if (preg_match('/^\d{4}-\d{2}$/', $value) === 1) {
            return CarbonImmutable::parse($value.'-01', $timezone);
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return CarbonImmutable::parse($value, $timezone);
        }

        return null;
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
