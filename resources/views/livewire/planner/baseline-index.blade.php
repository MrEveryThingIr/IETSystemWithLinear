<section class="mx-auto max-w-7xl space-y-5">
    <header class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="text-2xl font-semibold">{{ __('planning_baseline.title') }}</h1>
            <p class="mt-1 text-sm text-zinc-500">{{ __('planning_baseline.help') }}</p>
        </div>
        <a
            href="{{ route('planner.create') }}"
            class="inline-flex items-center justify-center rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white dark:bg-zinc-100 dark:text-zinc-900"
        >
            {{ __('planning_baseline.new') }}
        </a>
    </header>

    <nav class="flex flex-wrap gap-2" aria-label="{{ __('planning_baseline.title') }}">
        <button type="button" wire:click="$set('view', 'today')" class="rounded-lg px-3 py-2 text-sm {{ $view === 'today' ? 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' : 'bg-zinc-100 dark:bg-zinc-900' }}">
            {{ __('planning_baseline.views.today') }}
        </button>
        <button type="button" wire:click="$set('view', 'list')" class="rounded-lg px-3 py-2 text-sm {{ $view === 'list' ? 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' : 'bg-zinc-100 dark:bg-zinc-900' }}">
            {{ __('planning_baseline.views.list') }}
        </button>
        <button type="button" wire:click="$set('view', 'calendar')" class="rounded-lg px-3 py-2 text-sm {{ $view === 'calendar' ? 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' : 'bg-zinc-100 dark:bg-zinc-900' }}">
            {{ __('planning_baseline.views.calendar') }}
        </button>
    </nav>

    <div class="grid gap-3 rounded-xl border border-zinc-200 p-4 md:grid-cols-[minmax(0,1fr)_12rem_12rem] dark:border-zinc-800">
        <label class="space-y-1 text-sm">
            <span class="font-medium">{{ __('planning_baseline.filters.search') }}</span>
            <input
                type="search"
                wire:model.live.debounce.300ms="search"
                placeholder="{{ __('planning_baseline.filters.search_placeholder') }}"
                class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 dark:border-zinc-700 dark:bg-zinc-950"
            >
        </label>

        <label class="space-y-1 text-sm">
            <span class="font-medium">{{ __('planning_baseline.filters.timing') }}</span>
            <select wire:model.live="timing" class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 dark:border-zinc-700 dark:bg-zinc-950">
                <option value="all">{{ __('planning_baseline.filters.all_timing') }}</option>
                <option value="fixed">{{ __('planning_baseline.timing.fixed') }}</option>
                <option value="flexible_day">{{ __('planning_baseline.timing.flexible_day') }}</option>
            </select>
        </label>

        <label class="space-y-1 text-sm">
            <span class="font-medium">{{ __('planning_baseline.filters.category') }}</span>
            <select wire:model.live="category" class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 dark:border-zinc-700 dark:bg-zinc-950">
                <option value="">{{ __('planning_baseline.filters.all_categories') }}</option>
                @foreach ($categories as $availableCategory)
                    <option value="{{ $availableCategory }}">{{ $availableCategory }}</option>
                @endforeach
            </select>
        </label>
    </div>

    @if ($view === 'today' || $view === 'list')
        <div class="space-y-2">
            @forelse ($occurrences as $occurrence)
                <a
                    href="{{ route('planner.show', $occurrence->plan) }}"
                    class="flex items-center justify-between gap-4 rounded-xl border border-zinc-200 bg-white p-4 hover:bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-950 dark:hover:bg-zinc-900"
                >
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="truncate font-medium" dir="auto">{{ $occurrence->plan->title }}</span>
                            @if (filled(data_get($occurrence->plan->metadata, 'category')))
                                <span class="rounded-full bg-zinc-100 px-2 py-0.5 text-xs dark:bg-zinc-800">{{ data_get($occurrence->plan->metadata, 'category') }}</span>
                            @endif
                        </div>
                        <div class="mt-1 text-sm text-zinc-500">
                            @if ($view === 'list')
                                <x-app.local-date :value="$occurrence->scheduled_start_at" :show-equivalent="false" /> ·
                            @endif

                            @if (($occurrence->scheduleRule?->timing_mode->value ?? 'fixed') === 'flexible_day')
                                {{ __('planning_baseline.timing.flexible_day_short') }}
                            @else
                                <x-app.local-time :value="$occurrence->scheduled_start_at" />
                                –
                                <x-app.local-time :value="$occurrence->scheduled_end_at" />
                            @endif
                        </div>
                    </div>
                    <span class="rounded-full bg-zinc-100 px-2 py-1 text-xs dark:bg-zinc-800">
                        {{ __('planner.occurrence_status.'.$occurrence->status->value) }}
                    </span>
                </a>
            @empty
                <div class="rounded-xl border border-dashed border-zinc-300 p-8 text-center dark:border-zinc-700">
                    <div class="font-medium">{{ $view === 'today' ? __('planning_baseline.empty.today') : __('planning_baseline.empty.list') }}</div>
                    <div class="mt-1 text-sm text-zinc-500">{{ __('planning_baseline.empty.help') }}</div>
                </div>
            @endforelse
        </div>
    @endif

    @if ($view === 'calendar')
        <div class="space-y-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <button type="button" wire:click="previousPeriod" class="rounded-lg bg-zinc-100 px-3 py-2 text-sm dark:bg-zinc-900">
                    {{ __('planner.calendar.previous') }}
                </button>

                <div class="flex flex-wrap items-center justify-center gap-2 text-sm">
                    <button type="button" wire:click="showYear('{{ $year }}')" class="font-medium">{{ $calendarYearLabel }}</button>

                    @if ($calendarLevel !== 'year')
                        <span>/</span>
                        <button type="button" wire:click="showMonth('{{ $month }}')" class="font-medium">{{ $calendarMonthLabel }}</button>
                    @endif

                    @if ($calendarLevel === 'day' || $calendarLevel === 'hour')
                        <span>/</span>
                        <button type="button" wire:click="showDay('{{ $day }}')" class="font-medium">
                            <x-app.local-date :value="$day" :show-equivalent="false" />
                        </button>
                    @endif

                    @if ($calendarLevel === 'hour')
                        <span>/</span>
                        <span>{{ str_pad((string) $hour, 2, '0', STR_PAD_LEFT) }}:00</span>
                    @endif
                </div>

                <button type="button" wire:click="nextPeriod" class="rounded-lg bg-zinc-100 px-3 py-2 text-sm dark:bg-zinc-900">
                    {{ __('planner.calendar.next') }}
                </button>
            </div>

            @if ($calendarLevel === 'year')
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    @foreach ($calendarMonths as $calendarMonth)
                        <button type="button" wire:click="showMonth('{{ $calendarMonth['key'] }}')" class="rounded-xl border border-zinc-200 p-4 text-start hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-900">
                            <span class="block font-semibold">{{ $calendarMonth['label'] }}</span>
                            <span class="mt-1 block text-sm text-zinc-500">
                                {{ trans_choice('planning_baseline.calendar.items', $calendarMonth['count'], ['count' => $calendarMonth['count']]) }}
                            </span>
                        </button>
                    @endforeach
                </div>
            @endif

            @if ($calendarLevel === 'month')
                <div class="grid grid-cols-7 gap-px overflow-hidden rounded-xl bg-zinc-200 dark:bg-zinc-800">
                    @foreach ($calendarDays as $calendarDay)
                        <button
                            type="button"
                            wire:click="showDay('{{ $calendarDay['key'] }}')"
                            class="min-h-24 bg-white p-2 text-start hover:bg-zinc-50 dark:bg-zinc-950 dark:hover:bg-zinc-900 {{ $calendarDay['in_month'] ? '' : 'opacity-45' }}"
                        >
                            <span class="text-xs font-medium">{{ $calendarDay['label'] }}</span>
                            @if ($calendarDay['count'] > 0)
                                <span class="mt-2 block text-sm font-semibold">{{ $calendarDay['count'] }}</span>
                                <span class="mt-1 block text-[0.68rem] leading-4 text-zinc-500">
                                    @if ($calendarDay['fixed_count'] > 0)
                                        {{ __('planning_baseline.calendar.fixed_count', ['count' => $calendarDay['fixed_count']]) }}
                                    @endif
                                    @if ($calendarDay['fixed_count'] > 0 && $calendarDay['flexible_count'] > 0)
                                        ·
                                    @endif
                                    @if ($calendarDay['flexible_count'] > 0)
                                        {{ __('planning_baseline.calendar.flexible_count', ['count' => $calendarDay['flexible_count']]) }}
                                    @endif
                                </span>
                            @endif
                        </button>
                    @endforeach
                </div>
            @endif

            @if ($calendarLevel === 'day')
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-zinc-50 p-3 dark:bg-zinc-900">
                    <span class="font-medium"><x-app.local-date :value="$day" :show-equivalent="false" /></span>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('planner.create', ['date' => $day, 'timing' => 'flexible_day']) }}" class="rounded-lg bg-white px-3 py-2 text-sm dark:bg-zinc-950">
                            {{ __('planning_baseline.calendar.add_flexible') }}
                        </a>
                        <a href="{{ route('planner.create', ['date' => $day]) }}" class="rounded-lg bg-zinc-900 px-3 py-2 text-sm text-white dark:bg-zinc-100 dark:text-zinc-900">
                            {{ __('planning_baseline.calendar.add_fixed') }}
                        </a>
                    </div>
                </div>

                @if ($calendarFlexible->isNotEmpty())
                    <div class="space-y-2">
                        <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('planning_baseline.calendar.flexible_section') }}</div>
                        @foreach ($calendarFlexible as $item)
                            <a href="{{ route('planner.show', $item->plan) }}" class="flex items-center justify-between gap-3 rounded-xl border border-zinc-200 p-3 hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-900">
                                <span class="font-medium" dir="auto">{{ $item->plan->title }}</span>
                                <span class="text-xs text-zinc-500">{{ __('planning_baseline.timing.flexible_day_short') }}</span>
                            </a>
                        @endforeach
                    </div>
                @endif

                <div class="divide-y divide-zinc-200 overflow-hidden rounded-xl border border-zinc-200 dark:divide-zinc-800 dark:border-zinc-800">
                    @foreach ($baselineCalendarHours as $calendarHour)
                        <button
                            type="button"
                            wire:click="showHour('{{ $day }}', {{ $calendarHour['hour'] }})"
                            class="grid h-14 w-full grid-cols-[5rem_1fr] items-center bg-white text-start hover:bg-zinc-50 dark:bg-zinc-950 dark:hover:bg-zinc-900"
                        >
                            <span class="border-e border-zinc-200 px-3 text-xs font-medium tabular-nums text-zinc-500 dark:border-zinc-800">{{ $calendarHour['label'] }}</span>
                            <span class="px-3 text-sm">
                                @if ($calendarHour['count'] > 0)
                                    {{ trans_choice('planning_baseline.calendar.items', $calendarHour['count'], ['count' => $calendarHour['count']]) }}
                                @else
                                    <span class="text-zinc-400">{{ __('planning_baseline.calendar.empty') }}</span>
                                @endif
                            </span>
                        </button>
                    @endforeach
                </div>
            @endif

            @if ($calendarLevel === 'hour')
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-zinc-50 p-3 dark:bg-zinc-900">
                    <div class="text-sm font-medium">{{ str_pad((string) $hour, 2, '0', STR_PAD_LEFT) }}:00</div>
                    <div class="flex flex-wrap gap-1">
                        <button type="button" wire:click="setSlotMinutes(60)" class="rounded-lg px-2 py-1 text-sm {{ $slotMinutes === 60 ? 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' : 'bg-white dark:bg-zinc-950' }}">60m</button>
                        <button type="button" wire:click="setSlotMinutes(30)" class="rounded-lg px-2 py-1 text-sm {{ $slotMinutes === 30 ? 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' : 'bg-white dark:bg-zinc-950' }}">30m</button>
                        <button type="button" wire:click="setSlotMinutes(15)" class="rounded-lg px-2 py-1 text-sm {{ $slotMinutes === 15 ? 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' : 'bg-white dark:bg-zinc-950' }}">15m</button>
                        <button type="button" wire:click="setSlotMinutes(5)" class="rounded-lg px-2 py-1 text-sm {{ $slotMinutes === 5 ? 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' : 'bg-white dark:bg-zinc-950' }}">5m</button>
                        <button type="button" wire:click="setSlotMinutes(1)" class="rounded-lg px-2 py-1 text-sm {{ $slotMinutes === 1 ? 'bg-zinc-900 text-white dark:bg-zinc-100 dark:text-zinc-900' : 'bg-white dark:bg-zinc-950' }}">1m</button>
                    </div>
                </div>

                <div class="divide-y divide-zinc-200 overflow-hidden rounded-xl border border-zinc-200 dark:divide-zinc-800 dark:border-zinc-800">
                    @foreach ($baselineCalendarSlots as $slot)
                        <div class="grid min-h-14 grid-cols-[6rem_1fr_auto] items-stretch bg-white dark:bg-zinc-950">
                            <div class="border-e border-zinc-200 px-3 py-3 text-xs font-medium tabular-nums text-zinc-500 dark:border-zinc-800">{{ $slot['time'] }}</div>
                            <button type="button" wire:click="selectSlot({{ $slot['minute'] }})" class="px-3 py-2 text-start hover:bg-zinc-50 dark:hover:bg-zinc-900">
                                @if ($slot['count'] > 0)
                                    {{ trans_choice('planning_baseline.calendar.items', $slot['count'], ['count' => $slot['count']]) }}
                                @else
                                    <span class="text-xs text-zinc-400">{{ __('planning_baseline.calendar.empty') }}</span>
                                @endif
                            </button>
                            <div class="p-2">
                                <a
                                    href="{{ route('planner.create', ['date' => $day, 'time' => $slot['time'], 'duration' => $slotMinutes]) }}"
                                    class="inline-flex size-8 items-center justify-center rounded-lg text-lg text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-800"
                                    aria-label="{{ __('planning_baseline.calendar.add_at', ['time' => $slot['time']]) }}"
                                >+</a>
                            </div>
                        </div>
                    @endforeach
                </div>

                @if ($selectedSlotItems->isNotEmpty())
                    <div class="space-y-2 rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
                        @foreach ($selectedSlotItems as $item)
                            <a href="{{ route('planner.show', $item->plan) }}" class="block rounded-lg p-2 hover:bg-zinc-50 dark:hover:bg-zinc-900">
                                <div class="font-medium" dir="auto">{{ $item->plan->title }}</div>
                                <div class="mt-1 text-xs text-zinc-500">
                                    <x-app.local-time :value="$item->scheduled_start_at" /> –
                                    <x-app.local-time :value="$item->scheduled_end_at" />
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            @endif
        </div>
    @endif
</section>
