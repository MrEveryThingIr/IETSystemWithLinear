<section class="mx-auto max-w-7xl space-y-6">
    <x-app.flash-message />

    <x-app.page-header :title="__('planner.title')" :description="__('planner.help')">
        <x-slot:actions>
            <flux:button
                :href="route('planner.create', $context ? ['context' => $context->uuid] : [])"
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
        <flux:card class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <flux:button wire:click="previousPeriod" variant="ghost" size="sm" icon="chevron-left">
                    {{ __('planner.calendar.previous') }}
                </flux:button>

                <div class="flex flex-wrap items-center justify-center gap-1">
                    <flux:button wire:click="showYear('{{ $year }}')" :variant="$calendarLevel === 'year' ? 'primary' : 'ghost'" size="sm">
                        {{ $year }}
                    </flux:button>
                    @if ($calendarLevel !== 'year')
                        <span class="text-zinc-300 dark:text-zinc-700">/</span>
                        <flux:button wire:click="showMonth('{{ $month }}')" :variant="$calendarLevel === 'month' ? 'primary' : 'ghost'" size="sm">
                            {{ \Carbon\CarbonImmutable::createFromFormat('!Y-m', $month, $timezone)->format('F') }}
                        </flux:button>
                    @endif
                    @if ($calendarLevel === 'day')
                        <span class="text-zinc-300 dark:text-zinc-700">/</span>
                        <flux:badge>{{ \Carbon\CarbonImmutable::parse($day, $timezone)->format('j') }}</flux:badge>
                    @endif
                </div>

                <flux:button wire:click="nextPeriod" variant="ghost" size="sm" icon-trailing="chevron-right">
                    {{ __('planner.calendar.next') }}
                </flux:button>
            </div>

            @if ($calendarLevel === 'year')
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    @foreach ($calendarMonths as $calendarMonth)
                        <button
                            type="button"
                            wire:key="calendar-month-{{ $calendarMonth['key'] }}"
                            wire:click="showMonth('{{ $calendarMonth['key'] }}')"
                            class="rounded-xl border border-zinc-200 p-4 text-start transition hover:border-zinc-400 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:border-zinc-500 dark:hover:bg-zinc-900"
                        >
                            <span class="block font-semibold">{{ $calendarMonth['date']->format('F') }}</span>
                            <span class="mt-1 block text-sm text-zinc-500">{{ trans_choice('planner.calendar.item_count', $calendarMonth['count'], ['count' => $calendarMonth['count']]) }}</span>
                        </button>
                    @endforeach
                </div>
            @elseif ($calendarLevel === 'day')
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-zinc-50 p-3 dark:bg-zinc-900">
                    <span
                        class="font-medium"
                        data-localized-date="{{ $day }}"
                        data-locale="{{ \App\Support\Localization::intlLocale() }}"
                        data-calendar="{{ \App\Support\TemporalPreferences::calendarFor(request()->user())->value }}"
                    >{{ $day }}</span>
                    <flux:button :href="route('planner.create', array_filter(['context' => $context?->uuid, 'date' => $day]))" size="sm" icon="plus">
                        {{ __('planner.calendar.add_to_day') }}
                    </flux:button>
                </div>

                <div class="divide-y divide-zinc-200 overflow-hidden rounded-xl border border-zinc-200 dark:divide-zinc-800 dark:border-zinc-800">
                    @for ($hour = 0; $hour < 24; $hour++)
                        @php
                            $items = $calendarHours->get($hour, collect());
                        @endphp
                        <div wire:key="calendar-hour-{{ $day }}-{{ $hour }}" class="grid min-h-16 grid-cols-[4rem_1fr] bg-white dark:bg-zinc-950">
                            <div class="border-e border-zinc-200 px-3 py-3 text-xs font-medium tabular-nums text-zinc-500 dark:border-zinc-800">
                                {{ str_pad((string) $hour, 2, '0', STR_PAD_LEFT) }}:00
                            </div>
                            <div class="space-y-2 p-2">
                                @foreach ($items as $occurrence)
                                    <a href="{{ route('planner.show', $occurrence->plan) }}#occurrence-{{ $occurrence->uuid }}" class="block rounded-lg border border-zinc-200 px-3 py-2 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-900">
                                        <span class="font-medium" dir="auto">{{ $occurrence->plan->title }}</span>
                                        <span class="ms-2 text-xs text-zinc-500">{{ $occurrence->scheduled_start_at->setTimezone($timezone)->format('H:i') }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endfor
                </div>
            @else
                <div class="grid grid-cols-7 gap-px overflow-hidden rounded-xl border border-zinc-200 bg-zinc-200 dark:border-zinc-700 dark:bg-zinc-700">
                    @foreach (\App\Support\TemporalPreferences::weekdayOrder(request()->user()?->locale) as $weekday)
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
                        <div wire:key="calendar-day-{{ $dateKey }}" class="min-h-32 bg-white p-2 dark:bg-zinc-950 {{ $inMonth ? '' : 'opacity-50' }}">
                            <button type="button" wire:click="showDay('{{ $dateKey }}')" class="rounded px-1 text-xs font-semibold text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900 dark:hover:bg-zinc-800 dark:hover:text-white">
                                {{ $date->format('j') }}
                            </button>
                            <div class="mt-2 space-y-1">
                                @foreach ($items as $occurrence)
                                    <a href="{{ route('planner.show', $occurrence->plan) }}#occurrence-{{ $occurrence->uuid }}" class="block rounded-lg border border-zinc-200 px-2 py-1 text-xs hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-900">
                                        <div class="font-medium" dir="auto">{{ $occurrence->plan->title }}</div>
                                        <div class="text-zinc-500">{{ $occurrence->scheduled_start_at->setTimezone($timezone)->format('H:i') }} · {{ __('planner.occurrence_status.'.$occurrence->status->value) }}</div>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
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
                <x-app.empty-state
                    :title="$view === 'today' ? __('planner.empty.today') : __('planner.empty.list')"
                />
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
