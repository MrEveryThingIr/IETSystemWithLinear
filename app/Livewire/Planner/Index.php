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

    #[Url(as: 'context')]
    public string $contextUuid = '';

    #[Url(as: 'date')]
    public string $selectedDate = '';

    public string $calendarTime = '09:00';

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

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->selectedDate) === 1) {
            try {
                $selected = CarbonImmutable::createFromFormat('!Y-m-d', $this->selectedDate, $timezone);
                if ($selected instanceof CarbonImmutable && $selected->format('Y-m-d') === $this->selectedDate) {
                    $this->month = $selected->format('Y-m');
                } else {
                    $this->selectedDate = '';
                }
            } catch (\Throwable) {
                $this->selectedDate = '';
            }
        } else {
            $this->selectedDate = '';
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
        $this->selectedDate = '';
    }

    public function previousYear(): void
    {
        $timezone = TemporalPreferences::timezoneFor($this->user());
        $this->month = CarbonImmutable::parse($this->month.'-01', $timezone)
            ->subYear()
            ->format('Y-m');
        $this->selectedDate = '';
    }

    public function nextYear(): void
    {
        $timezone = TemporalPreferences::timezoneFor($this->user());
        $this->month = CarbonImmutable::parse($this->month.'-01', $timezone)
            ->addYear()
            ->format('Y-m');
        $this->selectedDate = '';
    }

    public function goToToday(): void
    {
        $timezone = TemporalPreferences::timezoneFor($this->user());
        $today = CarbonImmutable::now($timezone);
        $this->month = $today->format('Y-m');
        $this->selectedDate = $today->format('Y-m-d');
    }

    public function selectCalendarDay(string $date): void
    {
        $timezone = TemporalPreferences::timezoneFor($this->user());

        try {
            $selected = CarbonImmutable::createFromFormat('!Y-m-d', $date, $timezone);
        } catch (\Throwable) {
            abort(422);
        }

        abort_unless($selected instanceof CarbonImmutable && $selected->format('Y-m-d') === $date, 422);

        $this->selectedDate = $date;
        $this->month = $selected->format('Y-m');
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
                'assets',
                'evidenceReferences.content.activeRevision',
                'evidenceReferences.revision',
            ])
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

        $selectedDayOccurrences = $this->selectedDate !== ''
            ? $calendarOccurrences->get($this->selectedDate, collect())
            : collect();

        return view('livewire.planner.index', [
            'plans' => $plans,
            'occurrences' => $occurrences,
            'calendarDays' => $calendarDays,
            'calendarOccurrences' => $calendarOccurrences,
            'selectedDayOccurrences' => $selectedDayOccurrences,
            'timezone' => $timezone,
            'context' => $context,
            'contextLabel' => $context instanceof Context ? $this->contextLabel($context) : null,
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

        $firstDay = TemporalPreferences::weekdayOrder($this->user()->locale)[0] ?? 1;
        $carbonFirstDay = $firstDay === 7 ? CarbonInterface::SUNDAY : $firstDay;
        $month = CarbonImmutable::parse($this->month.'-01', $timezone);

        return [
            $month->startOfMonth()->startOfWeek($carbonFirstDay),
            $month->endOfMonth()->endOfWeek($carbonFirstDay),
        ];
    }


    private function contextLabel(Context $context): string
    {
        $context->loadMissing([
            'relationshipBinding.relationship.purposeConcept.labels',
            'groupSpaceBinding.groupSpace.group',
        ]);

        return match ($context->kind->value) {
            'personal' => (string) __('planner.context.personal'),
            'relationship' => (string) __('planner.context.relationship', [
                'title' => $context->relationshipBinding?->relationship?->title
                    ?: $context->relationshipBinding?->relationship?->purposeConcept?->displayLabel()
                    ?: $context->uuid,
            ]),
            'group_space' => (string) __('planner.context.group_space', [
                'space' => $context->groupSpaceBinding?->groupSpace?->name ?: $context->uuid,
            ]),
            default => $context->kind->value,
        };
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
