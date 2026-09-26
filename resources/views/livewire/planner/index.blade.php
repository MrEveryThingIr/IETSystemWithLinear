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
        <flux:card class="space-y-5">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div class="flex flex-wrap items-center gap-1">
                    <flux:button wire:click="previousYear" variant="ghost" size="sm" :aria-label="__('planner.calendar.previous_year')">«</flux:button>
                    <flux:button wire:click="previousMonth" variant="ghost" size="sm">
                        ← {{ __('planner.calendar.previous') }}
                    </flux:button>
                </div>

                <div class="text-center">
                    <flux:heading size="lg">{{ \Carbon\CarbonImmutable::createFromFormat('!Y-m', $month, $timezone)->format('F Y') }}</flux:heading>
                    <div class="mt-1 text-xs text-zinc-500">{{ __('planner.calendar.month_internal_note') }}</div>
                </div>

                <div class="flex flex-wrap items-center justify-end gap-1">
                    <flux:button wire:click="nextMonth" variant="ghost" size="sm">
                        {{ __('planner.calendar.next') }} →
                    </flux:button>
                    <flux:button wire:click="nextYear" variant="ghost" size="sm" :aria-label="__('planner.calendar.next_year')">»</flux:button>
                </div>
            </div>

            <div class="grid gap-3 rounded-xl border border-zinc-200 p-3 dark:border-zinc-700 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
                <x-app.calendar-date-input model="selectedDate" :label="__('planner.calendar.jump_to_date')" />
                <flux:button wire:click="focusSelectedDate" variant="ghost">{{ __('planner.calendar.open_date') }}</flux:button>
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
                        $selected = $selectedDate === $dateKey;
                    @endphp
                    <div
                        wire:key="calendar-day-{{ $dateKey }}"
                        class="min-h-32 bg-white p-2 dark:bg-zinc-950 {{ $inMonth ? '' : 'opacity-50' }} {{ $selected ? 'ring-2 ring-inset ring-zinc-900 dark:ring-white' : '' }}"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <button
                                type="button"
                                wire:click="selectDate('{{ $dateKey }}')"
                                class="rounded-md px-1.5 py-1 text-xs font-semibold text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800"
                            >
                                {{ $date->format('j') }}
                            </button>
                            <a
                                href="{{ route('planner.create', array_filter([
                                    'context' => $context?->uuid,
                                    'date' => $dateKey,
                                    'time' => '09:00',
                                ])) }}"
                                class="rounded-md px-1.5 py-1 text-xs text-zinc-400 hover:bg-zinc-100 hover:text-zinc-900 dark:hover:bg-zinc-800 dark:hover:text-white"
                                aria-label="{{ __('planner.calendar.add_on_date', ['date' => $dateKey]) }}"
                            >+</a>
                        </div>

                        <div class="mt-2 space-y-1">
                            @foreach ($items as $occurrence)
                                @php($windowState = $occurrence->windowState())
                                <a
                                    href="{{ route('planner.show', $occurrence->plan) }}#occurrence-{{ $occurrence->uuid }}"
                                    class="block rounded-lg border border-zinc-200 px-2 py-1 text-xs hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-900"
                                >
                                    <div class="font-medium" dir="auto">{{ $occurrence->plan->title }}</div>
                                    <div class="text-zinc-500">
                                        {{ $occurrence->scheduled_start_at->setTimezone($timezone)->format('H:i') }}
                                        · {{ __('planner.window_state.'.$windowState->value) }}
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </flux:card>

        @if ($selectedDate !== '')
            <flux:card class="space-y-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <flux:heading size="lg">{{ __('planner.calendar.selected_day') }}</flux:heading>
                            @if ($selectedTemporalState)
                                <flux:badge>{{ __('planner.temporal_state.'.$selectedTemporalState) }}</flux:badge>
                            @endif
                        </div>
                        <div
                            class="mt-1 text-sm text-zinc-500"
                            data-localized-date="{{ $selectedDate }}"
                            data-locale="{{ \App\Support\Localization::intlLocale() }}"
                            data-calendar="{{ \App\Support\TemporalPreferences::calendarFor(request()->user())->value }}"
                        >{{ $selectedDate }}</div>
                        <div class="mt-1 text-xs text-zinc-500">{{ __('planner.calendar.selected_day_help') }}</div>
                    </div>

                    <flux:button
                        :href="route('planner.create', array_filter([
                            'context' => $context?->uuid,
                            'date' => $selectedDate,
                            'time' => '09:00',
                        ]))"
                        variant="primary"
                        icon="plus"
                    >
                        {{ __('planner.calendar.add_plan') }}
                    </flux:button>
                </div>

                <div class="max-h-[42rem] overflow-y-auto rounded-xl border border-zinc-200 dark:border-zinc-700">
                    @foreach (range(0, 23) as $hour)
                        @php
                            $hourValue = sprintf('%02d:00', $hour);
                            $hourItems = $selectedDayOccurrences->filter(
                                fn ($occurrence) => (int) $occurrence->scheduled_start_at->setTimezone($timezone)->format('G') === $hour
                            );
                        @endphp
                        <div class="grid min-h-16 grid-cols-[4.5rem_minmax(0,1fr)] border-b border-zinc-100 last:border-b-0 dark:border-zinc-800">
                            <div class="border-e border-zinc-100 px-3 py-3 text-xs font-medium text-zinc-500 dark:border-zinc-800">
                                {{ $hourValue }}
                            </div>
                            <div class="space-y-2 p-2">
                                @forelse ($hourItems as $occurrence)
                                    @php($windowState = $occurrence->windowState())
                                    <details class="rounded-lg border border-zinc-200 bg-white p-3 dark:border-zinc-700 dark:bg-zinc-950">
                                        <summary class="cursor-pointer list-none">
                                            <div class="flex flex-wrap items-center justify-between gap-2">
                                                <div class="min-w-0">
                                                    <a
                                                        href="{{ route('planner.show', $occurrence->plan) }}#occurrence-{{ $occurrence->uuid }}"
                                                        class="font-medium hover:underline"
                                                        dir="auto"
                                                    >{{ $occurrence->plan->title }}</a>
                                                    <div class="mt-1 text-xs text-zinc-500">
                                                        {{ $occurrence->scheduled_start_at->setTimezone($timezone)->format('H:i') }}
                                                        →
                                                        {{ $occurrence->scheduled_end_at->setTimezone($timezone)->format('H:i') }}
                                                    </div>
                                                </div>
                                                <flux:badge size="sm">{{ __('planner.window_state.'.$windowState->value) }}</flux:badge>
                                            </div>
                                        </summary>

                                        <div class="mt-3 space-y-3 border-t border-zinc-100 pt-3 text-sm dark:border-zinc-800">
                                            @if ($occurrence->plan->description)
                                                <div>
                                                    <div class="text-xs font-medium text-zinc-500">{{ __('planner.calendar.note') }}</div>
                                                    <div class="mt-1" dir="auto">{{ $occurrence->plan->description }}</div>
                                                </div>
                                            @endif

                                            <div>
                                                <div class="text-xs font-medium text-zinc-500">{{ __('planner.calendar.accessories') }}</div>
                                                <div class="mt-2 flex flex-wrap gap-2">
                                                    @foreach ($occurrence->assets as $asset)
                                                        <flux:badge size="sm">{{ $asset->original_filename }}</flux:badge>
                                                    @endforeach
                                                    @foreach ($occurrence->evidenceReferences as $reference)
                                                        <a href="{{ route('content-evidence.show', $reference) }}" class="text-xs underline">
                                                            {{ $reference->revision?->title ?: $reference->content?->activeRevision?->title ?: __('ui.content.untitled') }}
                                                        </a>
                                                    @endforeach
                                                    @if ($occurrence->assets->isEmpty() && $occurrence->evidenceReferences->isEmpty())
                                                        <span class="text-xs text-zinc-500">{{ __('planner.calendar.no_accessories') }}</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </details>
                                @empty
                                    <a
                                        href="{{ route('planner.create', array_filter([
                                            'context' => $context?->uuid,
                                            'date' => $selectedDate,
                                            'time' => $hourValue,
                                        ])) }}"
                                        class="inline-flex rounded-md px-2 py-1 text-xs text-zinc-400 hover:bg-zinc-100 hover:text-zinc-900 dark:hover:bg-zinc-800 dark:hover:text-white"
                                    >
                                        + {{ __('planner.calendar.plan_this_hour') }}
                                    </a>
                                @endforelse
                            </div>
                        </div>
                    @endforeach
                </div>

                <flux:callout>
                    {{ __('planner.calendar.minute_precision_help') }}
                </flux:callout>
            </flux:card>
        @endif
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
