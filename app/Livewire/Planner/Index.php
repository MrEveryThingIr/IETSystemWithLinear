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
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Planner')]
class Index extends Component
{
    #[Url]
    public string $view = 'today';

    #[Url]
    public string $month = '';

    #[Url(as: 'scale')]
    public string $calendarScale = 'month';

    #[Url]
    public string $day = '';

    #[Url]
    public int $hour = 0;

    #[Url(as: 'context')]
    public string $contextUuid = '';

    public function mount(): void
    {
        if (! in_array($this->view, ['today', 'list', 'calendar'], true)) {
            $this->view = 'today';
        }

        if (! in_array($this->calendarScale, ['year', 'month', 'day', 'hour'], true)) {
            $this->calendarScale = 'month';
        }

        $timezone = TemporalPreferences::timezoneFor($this->user());
        $now = CarbonImmutable::now($timezone);

        if (! preg_match('/^\d{4}-\d{2}$/', $this->month)) {
            $this->month = $now->format('Y-m');
        }

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->day)) {
            $this->day = $now->format('Y-m-d');
        }

        $this->hour = min(23, max(0, $this->hour));
    }

    public function previousMonth(): void
    {
        $timezone = TemporalPreferences::timezoneFor($this->user());
        $this->month = CarbonImmutable::parse($this->month.'-01', $timezone)->subMonth()->format('Y-m');
    }

    public function nextMonth(): void
    {
        $timezone = TemporalPreferences::timezoneFor($this->user());
        $this->month = CarbonImmutable::parse($this->month.'-01', $timezone)->addMonth()->format('Y-m');
    }

    public function previousYear(): void
    {
        $year = max(1, ((int) substr($this->month, 0, 4)) - 1);
        $this->month = sprintf('%04d-%s', $year, substr($this->month, 5, 2));
    }

    public function nextYear(): void
    {
        $year = ((int) substr($this->month, 0, 4)) + 1;
        $this->month = sprintf('%04d-%s', $year, substr($this->month, 5, 2));
    }

    public function showYear(): void
    {
        $this->calendarScale = 'year';
    }

    public function showMonth(int $month): void
    {
        abort_unless($month >= 1 && $month <= 12, 422);
        $year = (int) substr($this->month, 0, 4);
        $this->month = sprintf('%04d-%02d', $year, $month);
        $this->calendarScale = 'month';
    }

    public function showDay(string $day): void
    {
        $timezone = TemporalPreferences::timezoneFor($this->user());
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $day, $timezone);
        abort_unless($date !== false && $date->format('Y-m-d') === $day, 422);

        $this->day = $day;
        $this->month = $date->format('Y-m');
        $this->calendarScale = 'day';
    }

    public function showHour(int $hour): void
    {
        abort_unless($hour >= 0 && $hour <= 23, 422);
        $this->hour = $hour;
        $this->calendarScale = 'hour';
    }

    public function render(): View
    {
        $user = $this->user();
        $timezone = TemporalPreferences::timezoneFor($user);
        $context = $this->context($user);

        [$from, $through] = $this->window($timezone);

        $occurrences = PlanOccurrence::query()
            ->with(['plan.context', 'plan.participants.actor.user'])
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
        $yearMonths = collect();
        $dayHours = collect(range(0, 23));

        if ($this->view === 'calendar') {
            if ($this->calendarScale === 'month') {
                $firstDay = TemporalPreferences::weekdayOrder($user->locale)[0] ?? 1;
                $carbonFirstDay = $firstDay === 7 ? CarbonInterface::SUNDAY : $firstDay;
                $month = CarbonImmutable::parse($this->month.'-01', $timezone);
                $gridStart = $month->startOfMonth()->startOfWeek($carbonFirstDay);
                $gridEnd = $month->endOfMonth()->endOfWeek($carbonFirstDay);

                for ($date = $gridStart; $date->lte($gridEnd); $date = $date->addDay()) {
                    $calendarDays->push($date);
                }
            }

            if ($this->calendarScale === 'year') {
                $year = (int) substr($this->month, 0, 4);
                for ($number = 1; $number <= 12; $number++) {
                    $start = CarbonImmutable::create($year, $number, 1, 0, 0, 0, $timezone);
                    $yearMonths->push([
                        'number' => $number,
                        'start' => $start,
                        'count' => $occurrences->filter(
                            fn (PlanOccurrence $occurrence): bool => $occurrence->scheduled_start_at
                                ->setTimezone($timezone)
                                ->format('Y-m') === $start->format('Y-m'),
                        )->count(),
                    ]);
                }
            }

            $calendarOccurrences = $occurrences->groupBy(
                fn (PlanOccurrence $occurrence): string => $occurrence->scheduled_start_at
                    ->setTimezone($timezone)
                    ->format('Y-m-d'),
            );
        }

        return view('livewire.planner.index', [
            'plans' => $plans,
            'occurrences' => $occurrences,
            'calendarDays' => $calendarDays,
            'calendarOccurrences' => $calendarOccurrences,
            'yearMonths' => $yearMonths,
            'dayHours' => $dayHours,
            'timezone' => $timezone,
            'context' => $context,
        ]);
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

        if ($this->calendarScale === 'year') {
            $year = (int) substr($this->month, 0, 4);
            $date = CarbonImmutable::create($year, 1, 1, 0, 0, 0, $timezone);

            return [$date->startOfYear(), $date->endOfYear()];
        }

        if ($this->calendarScale === 'day' || $this->calendarScale === 'hour') {
            $date = CarbonImmutable::parse($this->day, $timezone);

            if ($this->calendarScale === 'hour') {
                $date = $date->setTime($this->hour, 0);

                return [$date->startOfHour(), $date->endOfHour()];
            }

            return [$date->startOfDay(), $date->endOfDay()];
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
