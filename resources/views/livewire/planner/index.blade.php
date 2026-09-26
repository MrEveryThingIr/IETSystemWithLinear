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
            <div class="flex items-center justify-between gap-3">
                <flux:button wire:click="previousMonth" variant="ghost" size="sm">
                    ← {{ __('planner.calendar.previous') }}
                </flux:button>
                <flux:heading
                    size="lg"
                    data-localized-calendar-month="{{ $month }}-01"
                    data-locale="{{ \App\Support\Localization::intlLocale() }}"
                    data-calendar="{{ \App\Support\TemporalPreferences::calendarFor(request()->user())->value }}"
                >
                    {{ \Carbon\CarbonImmutable::createFromFormat('!Y-m', $month, $timezone)->format('F Y') }}
                </flux:heading>
                <flux:button wire:click="nextMonth" variant="ghost" size="sm">
                    {{ __('planner.calendar.next') }} →
                </flux:button>
            </div>

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
                    <div
                        wire:key="calendar-day-{{ $dateKey }}"
                        class="min-h-32 bg-white p-2 dark:bg-zinc-950 {{ $inMonth ? '' : 'opacity-50' }}"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <button
                                type="button"
                                wire:click="selectDate('{{ $dateKey }}')"
                                class="rounded-md px-1.5 py-1 text-xs font-semibold text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900 dark:hover:bg-zinc-900 dark:hover:text-white {{ $selectedDate === $dateKey ? 'ring-1 ring-zinc-400' : '' }}"
                                aria-label="{{ __('planner.calendar.open_day', ['date' => $dateKey]) }}"
                            >
                                <span
                                    data-localized-calendar-day="{{ $dateKey }}"
                                    data-locale="{{ \App\Support\Localization::intlLocale() }}"
                                    data-calendar="{{ \App\Support\TemporalPreferences::calendarFor(request()->user())->value }}"
                                >{{ $date->format('j') }}</span>
                            </button>
                            <a
                                href="{{ route('planner.create', array_filter([
                                    'context' => $context?->uuid,
                                    'date' => $dateKey,
                                    'time' => '09:00',
                                ])) }}"
                                class="rounded-md px-1.5 py-1 text-xs text-zinc-400 hover:bg-zinc-100 hover:text-zinc-900 dark:hover:bg-zinc-900 dark:hover:text-white"
                                aria-label="{{ __('planner.calendar.create_on_day', ['date' => $dateKey]) }}"
                            >+</a>
                        </div>
                        <div class="mt-2 space-y-1">
                            @foreach ($items as $occurrence)
                                <a
                                    href="{{ route('planner.show', $occurrence->plan) }}#occurrence-{{ $occurrence->uuid }}"
                                    class="block rounded-lg border border-zinc-200 px-2 py-1 text-xs hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-900"
                                >
                                    <div class="font-medium" dir="auto">{{ $occurrence->plan->title }}</div>
                                    <div class="text-zinc-500">
                                        {{ $occurrence->scheduled_start_at->setTimezone($timezone)->format('H:i') }}
                                        · {{ __('planner.occurrence_status.'.$occurrence->status->value) }}
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_18rem]">
                <div class="space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('planner.calendar.selected_day') }}</div>
                            <div
                                class="mt-1 font-semibold"
                                data-localized-date="{{ $selectedDate }}"
                                data-locale="{{ \App\Support\Localization::intlLocale() }}"
                                data-calendar="{{ \App\Support\TemporalPreferences::calendarFor(request()->user())->value }}"
                            >{{ $selectedDate }}</div>
                            @if (\App\Support\TemporalPreferences::calendarFor(request()->user()) !== \App\CalendarSystem::Gregorian)
                                <div class="text-xs text-zinc-500">{{ __('planner.calendar.gregorian_equivalent', ['date' => $selectedDate]) }}</div>
                            @endif
                        </div>
                    </div>

                    <div class="space-y-2">
                        @forelse ($selectedOccurrences as $occurrence)
                            <a
                                href="{{ route('planner.show', $occurrence->plan) }}#occurrence-{{ $occurrence->uuid }}"
                                class="block rounded-xl border border-zinc-200 p-3 transition hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-900"
                            >
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div class="font-medium" dir="auto">{{ $occurrence->plan->title }}</div>
                                    <flux:badge size="sm">{{ __('planner.occurrence_phase.'.$occurrence->executionPhase()) }}</flux:badge>
                                </div>
                                <div class="mt-1 text-xs text-zinc-500">
                                    {{ $occurrence->scheduled_start_at->setTimezone($timezone)->format('H:i') }}
                                    →
                                    {{ $occurrence->scheduled_end_at->setTimezone($timezone)->format('H:i') }}
                                    @if ($occurrence->assets->isNotEmpty() || $occurrence->evidenceReferences->isNotEmpty())
                                        · {{ __('planner.calendar.accessory_count', ['count' => $occurrence->assets->count() + $occurrence->evidenceReferences->count()]) }}
                                    @endif
                                </div>
                            </a>
                        @empty
                            <x-app.empty-state :title="__('planner.calendar.no_items_on_day')" />
                        @endforelse
                    </div>
                </div>

                <flux:card class="space-y-3">
                    <div>
                        <flux:heading>{{ __('planner.calendar.plan_this_day') }}</flux:heading>
                        <flux:text>{{ __('planner.calendar.plan_this_day_help') }}</flux:text>
                    </div>
                    <flux:input wire:model="selectedTime" type="time" step="60" :label="__('planner.create.start_time')" />
                    <flux:button wire:click="createAtSelectedTime" variant="primary" class="w-full">
                        {{ __('planner.calendar.create_at_time') }}
                    </flux:button>
                </flux:card>
            </div>
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
