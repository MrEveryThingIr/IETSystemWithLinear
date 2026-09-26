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
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Planner')]
class Index extends Component
{
    #[Url]
    public string $view = 'today';

    /** Gregorian anchor for a month in the user's presentation calendar. */
    #[Url]
    public string $month = '';

    #[Url]
    public string $year = '';

    #[Url]
    public string $day = '';

    #[Url(as: 'level')]
    public string $calendarLevel = 'month';

    #[Url]
    public int $hour = 0;

    #[Url(as: 'quantum')]
    public int $slotMinutes = 15;

    #[Url(as: 'context')]
    public string $contextUuid = '';

    public function mount(): void
    {
        $this->normalizeSelection();
    }

    public function previousMonth(): void
    {
        $this->normalizeSelection();
        $user = $this->user();
        $timezone = TemporalPreferences::timezoneFor($user);
        $this->showMonth(TemporalCalendar::previousMonthStart(
            CarbonImmutable::parse($this->month, $timezone), $user, $timezone,
        )->toDateString());
    }

    public function nextMonth(): void
    {
        $this->normalizeSelection();
        $user = $this->user();
        $timezone = TemporalPreferences::timezoneFor($user);
        $this->showMonth(TemporalCalendar::nextMonthStart(
            CarbonImmutable::parse($this->month, $timezone), $user, $timezone,
        )->toDateString());
    }

    public function previousPeriod(): void
    {
        $this->movePeriod(-1);
    }

    public function nextPeriod(): void
    {
        $this->movePeriod(1);
    }

    private function movePeriod(int $direction): void
    {
        $this->normalizeSelection();
        $user = $this->user();
        $timezone = TemporalPreferences::timezoneFor($user);

        if ($this->calendarLevel === 'year') {
            $start = CarbonImmutable::parse($this->year, $timezone);
            $next = $direction > 0
                ? TemporalCalendar::nextYearStart($start, $user, $timezone)
                : TemporalCalendar::previousYearStart($start, $user, $timezone);
            $this->showYear($next->toDateString());
        } elseif ($this->calendarLevel === 'day') {
            $this->showDay(CarbonImmutable::parse($this->day, $timezone)->addDays($direction)->toDateString());
        } elseif ($this->calendarLevel === 'hour') {
            $next = CarbonImmutable::parse($this->day, $timezone)->setTime($this->hour, 0)->addHours($direction);
            $this->showHour($next->toDateString(), $next->hour);
        } else {
            $direction > 0 ? $this->nextMonth() : $this->previousMonth();
        }
    }

    public function showYear(?string $anchor = null): void
    {
        $user = $this->user();
        $timezone = TemporalPreferences::timezoneFor($user);
        $date = $this->dateAnchor($anchor ?? $this->month, $timezone) ?? CarbonImmutable::now($timezone);
        $start = TemporalCalendar::yearStart($date, $user, $timezone);
        $this->year = $start->toDateString();
        $this->month = $start->toDateString();
        $this->calendarLevel = 'year';
    }

    public function showMonth(string $anchor): void
    {
        $user = $this->user();
        $timezone = TemporalPreferences::timezoneFor($user);
        $date = $this->dateAnchor($anchor, $timezone);
        abort_unless($date instanceof CarbonImmutable, 422);

        $start = TemporalCalendar::monthStart($date, $user, $timezone);
        $this->month = $start->toDateString();
        $this->year = TemporalCalendar::yearStart($start, $user, $timezone)->toDateString();
        $this->calendarLevel = 'month';
    }

    public function showDay(string $day): void
    {
        $user = $this->user();
        $timezone = TemporalPreferences::timezoneFor($user);
        $date = $this->dateAnchor($day, $timezone);
        abort_unless($date instanceof CarbonImmutable && $date->toDateString() === $day, 422);

        $this->day = $day;
        $this->month = TemporalCalendar::monthStart($date, $user, $timezone)->toDateString();
        $this->year = TemporalCalendar::yearStart($date, $user, $timezone)->toDateString();
        $this->calendarLevel = 'day';
    }

    public function showHour(string $day, int $hour): void
    {
        abort_unless($hour >= 0 && $hour <= 23, 422);
        $this->showDay($day);
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
        $this->normalizeSelection();
        $user = $this->user();
        $timezone = TemporalPreferences::timezoneFor($user);
        $context = $this->context($user);
        [$from, $through] = $this->window($user, $timezone);

        // Authorization precedes the display cap: private rows must not displace visible rows.
        $occurrences = PlanOccurrence::query()
            ->with(['plan.context', 'plan.participants.actor.user'])
            ->when($context instanceof Context, fn ($query) => $query->whereHas(
                'plan', fn ($plans) => $plans->where('context_id', $context->id),
            ))
            ->whereBetween('scheduled_start_at', [$from->utc(), $through->utc()])
            ->orderBy('scheduled_start_at')
            ->get()
            ->filter(fn (PlanOccurrence $occurrence): bool => Gate::forUser($user)->allows('view', $occurrence->plan))
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

        if ($this->view === 'calendar') {
            if ($this->calendarLevel === 'year') {
                $cursor = $yearStart;
                for ($index = 0; $index < 12; $index++) {
                    $next = TemporalCalendar::nextMonthStart($cursor, $user, $timezone);
                    $calendarMonths->push([
                        'date' => $cursor->toDateString(),
                        'label' => TemporalCalendar::monthLabel($cursor, $user, $timezone),
                        'count' => $occurrences->filter(fn (PlanOccurrence $occurrence): bool =>
                            $occurrence->scheduled_start_at->setTimezone($timezone)->gte($cursor)
                            && $occurrence->scheduled_start_at->setTimezone($timezone)->lt($next))->count(),
                    ]);
                    $cursor = $next;
                }
            } elseif ($this->calendarLevel === 'day') {
                $calendarHours = $occurrences->groupBy(fn (PlanOccurrence $occurrence): int =>
                    (int) $occurrence->scheduled_start_at->setTimezone($timezone)->format('G'));
            } elseif ($this->calendarLevel === 'hour') {
                $start = CarbonImmutable::parse($this->day, $timezone)->setTime($this->hour, 0);
                for ($minute = 0; $minute < 60; $minute += $this->slotMinutes) {
                    $slotStart = $start->addMinutes($minute);
                    $slotEnd = $slotStart->addMinutes($this->slotMinutes);
                    $calendarSlots->push([
                        'time' => $slotStart->format('H:i'),
                        'items' => $occurrences->filter(fn (PlanOccurrence $occurrence): bool =>
                            $occurrence->scheduled_start_at->gte($slotStart)
                            && $occurrence->scheduled_start_at->lt($slotEnd))->values(),
                    ]);
                }
            } else {
                $first = TemporalPreferences::weekdayOrder($user->locale)[0] ?? 1;
                $first = $first === 7 ? CarbonInterface::SUNDAY : $first;
                $monthEnd = TemporalCalendar::nextMonthStart($monthStart, $user, $timezone)->subDay();
                $gridStart = $monthStart->startOfWeek($first);
                $gridEnd = $monthEnd->endOfWeek($first);
                $key = TemporalCalendar::monthKey($monthStart, $user, $timezone);

                for ($date = $gridStart; $date->lte($gridEnd); $date = $date->addDay()) {
                    $calendarDays->push([
                        'date' => $date->toDateString(),
                        'label' => TemporalCalendar::dayLabel($date, $user, $timezone),
                        'inMonth' => TemporalCalendar::monthKey($date, $user, $timezone) === $key,
                    ]);
                }

                $calendarOccurrences = $occurrences->groupBy(fn (PlanOccurrence $occurrence): string =>
                    $occurrence->scheduled_start_at->setTimezone($timezone)->toDateString());
            }
        }

        return view('livewire.planner.index', compact(
            'plans', 'occurrences', 'calendarDays', 'calendarOccurrences', 'calendarMonths',
            'calendarHours', 'calendarSlots', 'timezone', 'context',
        ) + [
            'calendarYearLabel' => TemporalCalendar::yearLabel($yearStart, $user, $timezone),
            'calendarMonthLabel' => TemporalCalendar::monthLabel($monthStart, $user, $timezone),
        ]);
    }

    /** @return array{CarbonImmutable, CarbonImmutable} */
    private function window(User $user, string $timezone): array
    {
        $now = CarbonImmutable::now($timezone);
        if ($this->view === 'today') {
            return [$now->startOfDay(), $now->endOfDay()];
        }
        if ($this->view === 'list') {
            return [$now->startOfDay(), $now->addDays(30)->endOfDay()];
        }
        if ($this->calendarLevel === 'year') {
            $start = CarbonImmutable::parse($this->year, $timezone);
            return [$start, TemporalCalendar::nextYearStart($start, $user, $timezone)->subMicrosecond()];
        }
        if ($this->calendarLevel === 'day') {
            $day = CarbonImmutable::parse($this->day, $timezone);
            return [$day->startOfDay(), $day->endOfDay()];
        }
        if ($this->calendarLevel === 'hour') {
            $hour = CarbonImmutable::parse($this->day, $timezone)->setTime($this->hour, 0);
            return [$hour->startOfHour(), $hour->endOfHour()];
        }
        $first = TemporalPreferences::weekdayOrder($user->locale)[0] ?? 1;
        $first = $first === 7 ? CarbonInterface::SUNDAY : $first;
        $month = CarbonImmutable::parse($this->month, $timezone);
        return [
            $month->startOfWeek($first),
            TemporalCalendar::nextMonthStart($month, $user, $timezone)->subDay()->endOfWeek($first),
        ];
    }

    private function normalizeSelection(): void
    {
        $user = $this->user();
        $timezone = TemporalPreferences::timezoneFor($user);
        $now = CarbonImmutable::now($timezone);
        $this->view = in_array($this->view, ['today', 'list', 'calendar'], true) ? $this->view : 'today';
        $this->calendarLevel = in_array($this->calendarLevel, ['year', 'month', 'day', 'hour'], true) ? $this->calendarLevel : 'month';
        $this->hour = min(23, max(0, $this->hour));
        $this->slotMinutes = in_array($this->slotMinutes, [60, 30, 15, 5, 1], true) ? $this->slotMinutes : 15;
        $month = $this->dateAnchor($this->month, $timezone) ?? $now;
        $this->month = TemporalCalendar::monthStart($month, $user, $timezone)->toDateString();
        $year = $this->dateAnchor($this->year, $timezone) ?? $month;
        $this->year = TemporalCalendar::yearStart($year, $user, $timezone)->toDateString();
        $day = $this->dateAnchor($this->day, $timezone) ?? $now;
        $this->day = $day->toDateString();
    }

    private function dateAnchor(?string $value, string $timezone): ?CarbonImmutable
    {
        if (preg_match('/^(1\d{3}|[2-9]\d{3})-(\d{2})(?:-(\d{2}))?$/', $value ?? '', $match) !== 1) {
            return null;
        }
        $day = (int) ($match[3] ?? 1);
        if (! checkdate((int) $match[2], $day, (int) $match[1])) {
            return null;
        }
        return CarbonImmutable::parse(sprintf('%s-%s-%02d', $match[1], $match[2], $day), $timezone);
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
