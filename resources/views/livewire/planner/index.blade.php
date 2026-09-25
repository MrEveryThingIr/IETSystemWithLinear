<section class="mx-auto max-w-7xl space-y-6">
    <x-app.flash-message />

    <x-app.page-header :title="__('planner.title')" :description="__('planner.help')">
        <x-slot:actions>
            <flux:button
                :href="route('planner.create', array_filter([
                    'context' => $context?->uuid,
                    'date' => $view === 'calendar' && in_array($calendarScale, ['day', 'hour'], true) ? $day : null,
                    'time' => $view === 'calendar' && $calendarScale === 'hour' ? sprintf('%02d:00', $hour) : null,
                ]))"
                variant="primary"
                icon="plus"
            >
                {{ __('planner.new') }}
            </flux:button>
        </x-slot:actions>
    </x-app.page-header>

    @if ($context)
        <flux:callout>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <span>{{ __('planner.create.context') }} · {{ $context->kind->value }}</span>
                <flux:button :href="route('planner.index')" size="sm" variant="ghost">
                    {{ __('planner.context.all') }}
                </flux:button>
            </div>
        </flux:callout>
    @endif

    <div class="flex flex-wrap gap-2">
        @foreach (['today', 'list', 'calendar'] as $mode)
            <flux:button
                wire:click="$set('view', '{{ $mode }}')"
                :variant="$view === $mode ? 'primary' : 'ghost'"
                size="sm"
            >
                {{ __('planner.views.'.$mode) }}
            </flux:button>
        @endforeach
    </div>

    @if ($view === 'calendar')
        <flux:card class="space-y-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap gap-2">
                    <flux:button wire:click="showYear" size="sm" :variant="$calendarScale === 'year' ? 'primary' : 'ghost'">
                        {{ __('planner.calendar.year') }}
                    </flux:button>
                    <flux:button wire:click="$set('calendarScale', 'month')" size="sm" :variant="$calendarScale === 'month' ? 'primary' : 'ghost'">
                        {{ __('planner.calendar.month') }}
                    </flux:button>
                    @if (in_array($calendarScale, ['day', 'hour'], true))
                        <flux:button wire:click="$set('calendarScale', 'day')" size="sm" :variant="$calendarScale === 'day' ? 'primary' : 'ghost'">
                            {{ __('planner.calendar.day') }}
                        </flux:button>
                    @endif
                    @if ($calendarScale === 'hour')
                        <flux:button size="sm" variant="primary">{{ __('planner.calendar.hour') }}</flux:button>
                    @endif
                </div>

                <div class="text-sm text-zinc-500">{{ __('planner.calendar.drill_help') }}</div>
            </div>

            @if ($calendarScale === 'year')
                @php $year = (int) substr($month, 0, 4); @endphp
                <div class="flex items-center justify-between gap-3">
                    <flux:button wire:click="previousYear" variant="ghost" size="sm">← {{ __('planner.calendar.previous_year') }}</flux:button>
                    <flux:heading size="lg">{{ $year }}</flux:heading>
                    <flux:button wire:click="nextYear" variant="ghost" size="sm">{{ __('planner.calendar.next_year') }} →</flux:button>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    @foreach ($yearMonths as $item)
                        <button
                            type="button"
                            wire:click="showMonth({{ $item['number'] }})"
                            class="rounded-xl border border-zinc-200 p-4 text-start transition hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-900"
                        >
                            <div class="font-semibold">{{ $item['start']->format('F') }}</div>
                            <div class="mt-2 text-sm text-zinc-500">{{ trans_choice('planner.calendar.occurrence_count', $item['count'], ['count' => $item['count']]) }}</div>
                        </button>
                    @endforeach
                </div>
            @elseif ($calendarScale === 'month')
                <div class="flex items-center justify-between gap-3">
                    <flux:button wire:click="previousMonth" variant="ghost" size="sm">
                        ← {{ __('planner.calendar.previous') }}
                    </flux:button>
                    <button type="button" wire:click="showYear" class="font-semibold hover:underline">
                        {{ CarbonCarbonImmutable::createFromFormat('!Y-m', $month, $timezone)->format('F Y') }}
                    </button>
                    <flux:button wire:click="nextMonth" variant="ghost" size="sm">
                        {{ __('planner.calendar.next') }} →
                    </flux:button>
                </div>

                <div class="grid grid-cols-7 gap-px overflow-hidden rounded-xl border border-zinc-200 bg-zinc-200 dark:border-zinc-700 dark:bg-zinc-700">
                    @foreach (AppSupportTemporalPreferences::weekdayOrder(request()->user()?->locale) as $weekday)
                        <div class="bg-zinc-50 px-2 py-2 text-center text-xs font-medium text-zinc-500 dark:bg-zinc-900">
                            {{ __('planner.weekdays.'.$weekday) }}
                        </div>
                    @endforeach

                    @foreach ($calendarDays as $date)
                        @php
                            $dateKey = $date->format('Y-m-d');
                            $items = $calendarOccurrences->get($dateKey, collect());
                            $inMonth = $date->format('Y-m') === $month;
                        @endphp
                        <button
                            type="button"
                            wire:key="calendar-day-{{ $dateKey }}"
                            wire:click="showDay('{{ $dateKey }}')"
                            class="min-h-32 bg-white p-2 text-start transition hover:bg-zinc-50 dark:bg-zinc-950 dark:hover:bg-zinc-900 {{ $inMonth ? '' : 'opacity-50' }}"
                        >
                            <div class="text-xs font-semibold text-zinc-500">{{ $date->format('j') }}</div>
                            <div class="mt-2 space-y-1">
                                @foreach ($items->take(3) as $occurrence)
                                    <div class="rounded-lg border border-zinc-200 px-2 py-1 text-xs dark:border-zinc-700">
                                        <div class="truncate font-medium" dir="auto">{{ $occurrence->plan->title }}</div>
                                        <div class="text-zinc-500">{{ $occurrence->scheduled_start_at->setTimezone($timezone)->format('H:i') }}</div>
                                    </div>
                                @endforeach
                                @if ($items->count() > 3)
                                    <div class="text-xs text-zinc-500">+{{ $items->count() - 3 }}</div>
                                @endif
                            </div>
                        </button>
                    @endforeach
                </div>
            @elseif ($calendarScale === 'day')
                @php
                    $selectedDate = CarbonCarbonImmutable::parse($day, $timezone);
                    $dayItems = $calendarOccurrences->get($day, collect());
                @endphp
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <flux:button wire:click="$set('calendarScale', 'month')" variant="ghost" size="sm">← {{ __('planner.calendar.back_to_month') }}</flux:button>
                    <flux:heading size="lg">{{ $selectedDate->format('l, F j, Y') }}</flux:heading>
                    <flux:button :href="route('planner.create', array_filter(['context' => $context?->uuid, 'date' => $day]))" size="sm" variant="primary" icon="plus">
                        {{ __('planner.calendar.add_on_day') }}
                    </flux:button>
                </div>

                <div class="space-y-1">
                    @foreach ($dayHours as $hourNumber)
                        @php
                            $hourItems = $dayItems->filter(fn ($occurrence) => (int) $occurrence->scheduled_start_at->setTimezone($timezone)->format('G') === $hourNumber);
                        @endphp
                        <button
                            type="button"
                            wire:click="showHour({{ $hourNumber }})"
                            class="grid w-full grid-cols-[5rem_1fr] gap-3 rounded-lg px-3 py-2 text-start hover:bg-zinc-50 dark:hover:bg-zinc-900"
                        >
                            <span class="text-sm font-medium text-zinc-500">{{ sprintf('%02d:00', $hourNumber) }}</span>
                            <span class="min-w-0">
                                @forelse ($hourItems as $occurrence)
                                    <span class="me-2 inline-block rounded-md border border-zinc-200 px-2 py-1 text-xs dark:border-zinc-700">{{ $occurrence->plan->title }}</span>
                                @empty
                                    <span class="text-xs text-zinc-400">{{ __('planner.calendar.free_hour') }}</span>
                                @endforelse
                            </span>
                        </button>
                    @endforeach
                </div>
            @else
                @php
                    $hourItems = $occurrences;
                    $selectedDate = CarbonCarbonImmutable::parse($day, $timezone)->setTime($hour, 0);
                @endphp
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <flux:button wire:click="$set('calendarScale', 'day')" variant="ghost" size="sm">← {{ __('planner.calendar.back_to_day') }}</flux:button>
                    <flux:heading size="lg">{{ $selectedDate->format('F j, Y · H:00') }}</flux:heading>
                    <flux:button :href="route('planner.create', array_filter(['context' => $context?->uuid, 'date' => $day, 'time' => sprintf('%02d:00', $hour)]))" size="sm" variant="primary" icon="plus">
                        {{ __('planner.calendar.add_on_hour') }}
                    </flux:button>
                </div>

                <div class="space-y-3">
                    @forelse ($hourItems as $occurrence)
                        <a href="{{ route('planner.show', $occurrence->plan) }}#occurrence-{{ $occurrence->uuid }}" class="block rounded-xl border border-zinc-200 p-4 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-900">
                            <div class="font-semibold" dir="auto">{{ $occurrence->plan->title }}</div>
                            <div class="mt-1 text-sm text-zinc-500">
                                {{ $occurrence->scheduled_start_at->setTimezone($timezone)->format('H:i') }}
                                → {{ $occurrence->scheduled_end_at->setTimezone($timezone)->format('H:i') }}
                            </div>
                        </a>
                    @empty
                        <x-app.empty-state :title="__('planner.calendar.no_hour_activity')" />
                    @endforelse
                </div>
            @endif
        </flux:card>
    @else
        <div class="space-y-3">
            @forelse ($occurrences as $occurrence)
                <article
                    wire:key="planner-occurrence-{{ $occurrence->uuid }}"
                    class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-950"
                >
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <flux:badge>{{ __('planner.occurrence_status.'.$occurrence->status->value) }}</flux:badge>
                                <span class="text-xs text-zinc-500">{{ $occurrence->plan->timezone }}</span>
                            </div>
                            <a href="{{ route('planner.show', $occurrence->plan) }}#occurrence-{{ $occurrence->uuid }}" class="mt-2 block text-lg font-semibold hover:underline" dir="auto">
                                {{ $occurrence->plan->title }}
                            </a>
                            @if ($occurrence->plan->description)
                                <p class="mt-1 line-clamp-2 text-sm text-zinc-600 dark:text-zinc-300" dir="auto">{{ $occurrence->plan->description }}</p>
                            @endif
                        </div>

                        <div class="shrink-0 text-sm sm:text-end">
                            <div class="font-medium">{{ $occurrence->scheduled_start_at->setTimezone($timezone)->format('Y-m-d H:i') }}</div>
                            <div class="text-zinc-500">→ {{ $occurrence->scheduled_end_at->setTimezone($timezone)->format('H:i') }}</div>
                        </div>
                    </div>
                </article>
            @empty
                <x-app.empty-state :title="$view === 'today' ? __('planner.empty.today') : __('planner.empty.list')" />
            @endforelse
        </div>
    @endif

    <flux:card class="space-y-4">
        <div class="flex items-center justify-between gap-3">
            <flux:heading size="lg">{{ __('planner.plan.schedule_rules') }}</flux:heading>
            <span class="text-sm text-zinc-500">{{ $plans->count() }}</span>
        </div>

        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($plans as $plan)
                <a
                    wire:key="planner-plan-{{ $plan->uuid }}"
                    href="{{ route('planner.show', $plan) }}"
                    class="rounded-xl border border-zinc-200 p-4 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-900"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="font-semibold" dir="auto">{{ $plan->title }}</div>
                        <flux:badge size="sm">{{ __('planner.status.'.$plan->status->value) }}</flux:badge>
                    </div>
                    <div class="mt-2 text-xs text-zinc-500">{{ $plan->timezone }}</div>
                </a>
            @empty
                <div class="md:col-span-2 xl:col-span-3">
                    <x-app.empty-state :title="__('planner.empty.plans')" />
                </div>
            @endforelse
        </div>
    </flux:card>
</section>
