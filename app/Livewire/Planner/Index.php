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

    #[Url(as: 'level')]
    public string $calendarLevel = 'month';

    #[Url]
    public string $year = '';

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

        $timezone = TemporalPreferences::timezoneFor($this->user());
        $now = CarbonImmutable::now($timezone);

        if (! preg_match('/^\d{4}-\d{2}$/', $this->month)) {
            $this->month = $now->format('Y-m');
        }

        if (! in_array($this->calendarLevel, ['year', 'month', 'day', 'hour'], true)) {
            $this->calendarLevel = 'month';
        }

        if (! preg_match('/^\d{4}$/', $this->year)) {
            $this->year = substr($this->month, 0, 4);
        }

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

    public function previousPeriod(): void
    {
        $timezone = TemporalPreferences::timezoneFor($this->user());

        if ($this->calendarLevel === 'year') {
            $this->year = (string) ((int) $this->year - 1);

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
        $this->year = substr($this->month, 0, 4);
    }

    public function nextPeriod(): void
    {
        $timezone = TemporalPreferences::timezoneFor($this->user());

        if ($this->calendarLevel === 'year') {
            $this->year = (string) ((int) $this->year + 1);

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
        $this->year = substr($this->month, 0, 4);
    }

    public function showYear(?string $year = null): void
    {
        $candidate = $year ?? substr($this->month, 0, 4);
        abort_unless(preg_match('/^\d{4}$/', $candidate) === 1, 422);

        $this->year = $candidate;
        $this->calendarLevel = 'year';
    }

    public function showMonth(string $month): void
    {
        abort_unless(preg_match('/^\d{4}-\d{2}$/', $month) === 1, 422);

        $this->month = $month;
        $this->year = substr($month, 0, 4);
        $this->calendarLevel = 'month';
    }

    public function showDay(string $day): void
    {
        abort_unless(preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) === 1, 422);

        $this->day = $day;
        $this->month = substr($day, 0, 7);
        $this->year = substr($day, 0, 4);
        $this->calendarLevel = 'day';
    }

    public function showHour(string $day, int $hour): void
    {
        abort_unless(preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) === 1, 422);
        abort_unless($hour >= 0 && $hour <= 23, 422);

        $this->day = $day;
        $this->month = substr($day, 0, 7);
        $this->year = substr($day, 0, 4);
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

        if ($this->view === 'calendar') {
            if ($this->calendarLevel === 'year') {
                for ($monthNumber = 1; $monthNumber <= 12; $monthNumber++) {
                    $month = CarbonImmutable::create((int) $this->year, $monthNumber, 1, 0, 0, 0, $timezone);
                    $calendarMonths->push([
                        'date' => $month,
                        'key' => $month->format('Y-m'),
                        'count' => $occurrences->filter(fn (PlanOccurrence $occurrence): bool => $occurrence->scheduled_start_at
                            ->setTimezone($timezone)
                            ->format('Y-m') === $month->format('Y-m'))->count(),
                    ]);
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
        }

        return view('livewire.planner.index', [
            'plans' => $plans,
            'occurrences' => $occurrences,
            'calendarDays' => $calendarDays,
            'calendarOccurrences' => $calendarOccurrences,
            'calendarMonths' => $calendarMonths,
            'calendarHours' => $calendarHours,
            'calendarSlots' => $calendarSlots,
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

        if ($this->calendarLevel === 'year') {
            $year = CarbonImmutable::create((int) $this->year, 1, 1, 0, 0, 0, $timezone);

            return [$year->startOfYear(), $year->endOfYear()];
        }

        if ($this->calendarLevel === 'hour') {
            $hour = CarbonImmutable::parse($this->day, $timezone)->setTime($this->hour, 0);

            return [$hour->startOfHour(), $hour->endOfHour()];
        }

        if ($this->calendarLevel === 'day') {
            $day = CarbonImmutable::parse($this->day, $timezone);

            return [$day->startOfDay(), $day->endOfDay()];
        }

        $firstDay = TemporalPreferences::weekdayOrder($this->user()->locale)[0] ?? 1;
        $carbonFirstDay = $firstDay === 7 ? CarbonInterface::SUNDAY : $firstDay;
        $month = CarbonImmutable::parse($this->month.'-01', $timezone);

        return [
            $month->startOfMonth()->startOfWeek($carbonFirstDay),
            $month->endOfMonth()->endOfWeek($carbonFirstDay),
        ];
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
