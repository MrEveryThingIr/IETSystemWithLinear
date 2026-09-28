<section class="mx-auto max-w-7xl space-y-5">
    <x-app.page-header :title="__('planning_baseline.title')" :description="__('planning_baseline.help')">
        <x-slot:actions>
            <flux:button :href="route('planner.create')" variant="primary" icon="plus">
                {{ __('planning_baseline.new') }}
            </flux:button>
        </x-slot:actions>
    </x-app.page-header>

    <div class="flex flex-wrap gap-2">
        @foreach (['today', 'list', 'calendar'] as $mode)
            <flux:button
                wire:click="$set('view', '{{ $mode }}')"
                :variant="$view === $mode ? 'primary' : 'ghost'"
                size="sm"
            >
                {{ __('planning_baseline.views.'.$mode) }}
            </flux:button>
        @endforeach
    </div>

    <flux:card class="grid gap-3 md:grid-cols-[minmax(0,1fr)_12rem_12rem]">
        <flux:input
            wire:model.live.debounce.300ms="search"
            icon="magnifying-glass"
            :label="__('planning_baseline.filters.search')"
            :placeholder="__('planning_baseline.filters.search_placeholder')"
        />

        <flux:select wire:model.live="timing" :label="__('planning_baseline.filters.timing')">
            <option value="all">{{ __('planning_baseline.filters.all_timing') }}</option>
            <option value="fixed">{{ __('planning_baseline.timing.fixed') }}</option>
            <option value="flexible_day">{{ __('planning_baseline.timing.flexible_day') }}</option>
        </flux:select>

        <flux:select wire:model.live="category" :label="__('planning_baseline.filters.category')">
            <option value="">{{ __('planning_baseline.filters.all_categories') }}</option>
            @foreach ($categories as $availableCategory)
                <option value="{{ $availableCategory }}">{{ $availableCategory }}</option>
            @endforeach
        </flux:select>
    </flux:card>

    @if ($view !== 'calendar')
        <div class="space-y-2">
            @forelse ($occurrences as $occurrence)
                @php($mode = $occurrence->scheduleRule?->timing_mode?->value ?? 'fixed')
                <a
                    href="{{ route('planner.show', $occurrence->plan) }}"
                    class="flex items-center justify-between gap-4 rounded-xl border border-zinc-200 bg-white p-4 transition hover:bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-950 dark:hover:bg-zinc-900"
                >
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="truncate font-medium" dir="auto">{{ $occurrence->plan->title }}</span>
                            @if (filled(data_get($occurrence->plan->metadata, 'category')))
                                <flux:badge color="zinc">{{ data_get($occurrence->plan->metadata, 'category') }}</flux:badge>
                            @endif
                        </div>
                        <div class="mt-1 text-sm text-zinc-500">
                            @if ($view === 'list')
                                <x-app.local-date :value="$occurrence->scheduled_start_at" :show-equivalent="false" /> ·
                            @endif

                            @if ($mode === 'flexible_day')
                                {{ __('planning_baseline.timing.flexible_day_short') }}
                            @else
                                <x-app.local-time :value="$occurrence->scheduled_start_at" />
                                –
                                <x-app.local-time :value="$occurrence->scheduled_end_at" />
                            @endif
                        </div>
                    </div>
                    <flux:badge color="zinc">{{ __('planner.occurrence_status.'.$occurrence->status->value) }}</flux:badge>
                </a>
            @empty
                <x-app.empty-state
                    :title="$view === 'today' ? __('planning_baseline.empty.today') : __('planning_baseline.empty.list')"
                    :description="__('planning_baseline.empty.help')"
                />
            @endforelse
        </div>
    @else
        <flux:card class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <flux:button wire:click="previousPeriod" variant="ghost" size="sm" icon="chevron-left">
                    {{ __('planner.calendar.previous') }}
                </flux:button>

                <div class="flex flex-wrap items-center justify-center gap-1">
                    <flux:button wire:click="showYear('{{ $year }}')" :variant="$calendarLevel === 'year' ? 'primary' : 'ghost'" size="sm">
                        {{ $calendarYearLabel }}
                    </flux:button>

                    @if ($calendarLevel !== 'year')
                        <span class="text-zinc-300 dark:text-zinc-700">/</span>
                        <flux:button wire:click="showMonth('{{ $month }}')" :variant="$calendarLevel === 'month' ? 'primary' : 'ghost'" size="sm">
                            {{ $calendarMonthLabel }}
                        </flux:button>
                    @endif

                    @if (in_array($calendarLevel, ['day', 'hour'], true))
                        <span class="text-zinc-300 dark:text-zinc-700">/</span>
                        <flux:button wire:click="showDay('{{ $day }}')" :variant="$calendarLevel === 'day' ? 'primary' : 'ghost'" size="sm">
                            <x-app.local-date :value="$day" :show-equivalent="false" />
                        </flux:button>
                    @endif

                    @if ($calendarLevel === 'hour')
                        <span class="text-zinc-300 dark:text-zinc-700">/</span>
                        <flux:badge color="zinc">{{ str_pad((string) $hour, 2, '0', STR_PAD_LEFT) }}:00</flux:badge>
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
                            wire:key="baseline-calendar-month-{{ $calendarMonth['key'] }}"
                            wire:click="showMonth('{{ $calendarMonth['key'] }}')"
                            class="rounded-xl border border-zinc-200 p-4 text-start transition hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-900"
                        >
                            <span class="block font-semibold">{{ $calendarMonth['label'] }}</span>
                            <span class="mt-1 block text-sm text-zinc-500">
                                {{ trans_choice('planning_baseline.calendar.items', $calendarMonth['count'], ['count' => $calendarMonth['count']]) }}
                            </span>
                        </button>
                    @endforeach
                </div>
            @elseif ($calendarLevel === 'month')
                <div class="grid grid-cols-7 gap-px overflow-hidden rounded-xl bg-zinc-200 dark:bg-zinc-800">
                    @foreach ($calendarDays as $calendarDay)
                        @php
                            $dayItems = $calendarOccurrences->get($calendarDay['key'], collect());
                            $fixedCount = $dayItems->filter(fn ($item) => ($item->scheduleRule?->timing_mode?->value ?? 'fixed') === 'fixed')->count();
                            $flexibleCount = $dayItems->filter(fn ($item) => ($item->scheduleRule?->timing_mode?->value ?? 'fixed') === 'flexible_day')->count();
                        @endphp
                        <button
                            type="button"
                            wire:key="baseline-calendar-day-{{ $calendarDay['key'] }}"
                            wire:click="showDay('{{ $calendarDay['key'] }}')"
                            class="min-h-24 bg-white p-2 text-start transition hover:bg-zinc-50 dark:bg-zinc-950 dark:hover:bg-zinc-900 {{ $calendarDay['in_month'] ? '' : 'opacity-45' }}"
                        >
                            <span class="text-xs font-medium">{{ $calendarDay['label'] }}</span>
                            @if ($dayItems->isNotEmpty())
                                <span class="mt-2 block text-sm font-semibold">{{ $dayItems->count() }}</span>
                                <span class="mt-1 block text-[0.68rem] leading-4 text-zinc-500">
                                    @if ($fixedCount > 0)
                                        {{ __('planning_baseline.calendar.fixed_count', ['count' => $fixedCount]) }}
                                    @endif
                                    @if ($fixedCount > 0 && $flexibleCount > 0)
                                        ·
                                    @endif
                                    @if ($flexibleCount > 0)
                                        {{ __('planning_baseline.calendar.flexible_count', ['count' => $flexibleCount]) }}
                                    @endif
                                </span>
                            @endif
                        </button>
                    @endforeach
                </div>
            @elseif ($calendarLevel === 'day')
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-zinc-50 p-3 dark:bg-zinc-900">
                    <span class="font-medium"><x-app.local-date :value="$day" :show-equivalent="false" /></span>
                    <div class="flex flex-wrap gap-2">
                        <flux:button :href="route('planner.create', ['date' => $day, 'timing' => 'flexible_day'])" size="sm" variant="ghost">
                            {{ __('planning_baseline.calendar.add_flexible') }}
                        </flux:button>
                        <flux:button :href="route('planner.create', ['date' => $day])" size="sm" icon="plus">
                            {{ __('planning_baseline.calendar.add_fixed') }}
                        </flux:button>
                    </div>
                </div>

                @if ($calendarFlexible->isNotEmpty())
                    <div class="space-y-2">
                        <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('planning_baseline.calendar.flexible_section') }}</div>
                        @foreach ($calendarFlexible as $item)
                            <a href="{{ route('planner.show', $item->plan) }}" class="flex items-center justify-between gap-3 rounded-xl border border-zinc-200 p-3 hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-900">
                                <span class="font-medium" dir="auto">{{ $item->plan->title }}</span>
                                <flux:badge color="zinc">{{ __('planning_baseline.timing.flexible_day_short') }}</flux:badge>
                            </a>
                        @endforeach
                    </div>
                @endif

                <div class="divide-y divide-zinc-200 overflow-hidden rounded-xl border border-zinc-200 dark:divide-zinc-800 dark:border-zinc-800">
                    @for ($hourIndex = 0; $hourIndex < 24; $hourIndex++)
                        @php($items = $calendarHours->get($hourIndex, collect()))
                        <button
                            type="button"
                            wire:key="baseline-hour-{{ $day }}-{{ $hourIndex }}"
                            wire:click="showHour('{{ $day }}', {{ $hourIndex }})"
                            class="grid h-14 w-full grid-cols-[5rem_1fr] items-center bg-white text-start transition hover:bg-zinc-50 dark:bg-zinc-950 dark:hover:bg-zinc-900"
                        >
                            <span class="border-e border-zinc-200 px-3 text-xs font-medium tabular-nums text-zinc-500 dark:border-zinc-800">
                                {{ str_pad((string) $hourIndex, 2, '0', STR_PAD_LEFT) }}:00
                            </span>
                            <span class="px-3 text-sm">
                                @if ($items->isNotEmpty())
                                    {{ trans_choice('planning_baseline.calendar.items', $items->count(), ['count' => $items->count()]) }}
                                @else
                                    <span class="text-zinc-400">{{ __('planning_baseline.calendar.empty') }}</span>
                                @endif
                            </span>
                        </button>
                    @endfor
                </div>
            @elseif ($calendarLevel === 'hour')
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-zinc-50 p-3 dark:bg-zinc-900">
                    <div class="text-sm font-medium">{{ str_pad((string) $hour, 2, '0', STR_PAD_LEFT) }}:00</div>
                    <div class="flex flex-wrap items-center gap-1">
                        @foreach ([60, 30, 15, 5, 1] as $quantum)
                            <flux:button wire:click="setSlotMinutes({{ $quantum }})" :variant="$slotMinutes === $quantum ? 'primary' : 'ghost'" size="sm">
                                {{ __('planner.calendar.quantum_minutes', ['count' => $quantum]) }}
                            </flux:button>
                        @endforeach
                    </div>
                </div>

                <div class="divide-y divide-zinc-200 overflow-hidden rounded-xl border border-zinc-200 dark:divide-zinc-800 dark:border-zinc-800">
                    @foreach ($calendarSlots as $slot)
                        @php
                            $slotTime = $slot['start']->format('H:i');
                            $slotMinute = (int) $slot['start']->format('i');
                            $itemCount = $slot['items']->count();
                        @endphp
                        <div wire:key="baseline-slot-{{ $day }}-{{ $slotTime }}-{{ $slotMinutes }}" class="grid min-h-14 grid-cols-[6rem_1fr_auto] items-stretch bg-white dark:bg-zinc-950">
                            <div class="border-e border-zinc-200 px-3 py-3 text-xs font-medium tabular-nums text-zinc-500 dark:border-zinc-800">
                                {{ $slotTime }}
                            </div>
                            <button
                                type="button"
                                wire:click="selectSlot({{ $slotMinute }})"
                                class="px-3 py-2 text-start transition hover:bg-zinc-50 dark:hover:bg-zinc-900"
                            >
                                @if ($itemCount > 0)
                                    {{ trans_choice('planning_baseline.calendar.items', $itemCount, ['count' => $itemCount]) }}
                                @else
                                    <span class="text-xs text-zinc-400">{{ __('planning_baseline.calendar.empty') }}</span>
                                @endif
                            </button>
                            <div class="p-2">
                                <a
                                    href="{{ route('planner.create', ['date' => $day, 'time' => $slotTime, 'duration' => $slotMinutes]) }}"
                                    class="inline-flex size-8 items-center justify-center rounded-lg text-lg text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-900 dark:hover:bg-zinc-800 dark:hover:text-zinc-100"
                                    aria-label="{{ __('planning_baseline.calendar.add_at', ['time' => $slotTime]) }}"
                                >
                                    +
                                </a>
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
        </flux:card>
    @endif
</section>
