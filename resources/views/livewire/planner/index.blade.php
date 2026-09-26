<section class="mx-auto max-w-7xl space-y-6">
    <x-app.flash-message />

    <x-app.page-header :title="__('planner.title')" :description="__('planner.help')">
        <x-slot:actions>
            <flux:button :href="route('planner.create', $context ? ['context' => $context->uuid] : [])" variant="primary" icon="plus">
                {{ __('planner.new') }}
            </flux:button>
        </x-slot:actions>
    </x-app.page-header>

    @if ($context)
        <flux:callout>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <span>{{ __('planner.create.context') }} · {{ $context->kind->value }}</span>
                <flux:button :href="route('planner.index')" size="sm" variant="ghost">{{ __('planner.context.all') }}</flux:button>
            </div>
        </flux:callout>
    @endif

    <div class="flex flex-wrap gap-2">
        @foreach (['today', 'list', 'calendar'] as $mode)
            <flux:button wire:click="$set('view', '{{ $mode }}')" :variant="$view === $mode ? 'primary' : 'ghost'" size="sm">
                {{ __('planner.views.'.$mode) }}
            </flux:button>
        @endforeach
    </div>

    @if ($view === 'calendar')
        <flux:card class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <flux:button wire:click="previousPeriod" variant="ghost" size="sm" :aria-label="__('planner.calendar.previous')">‹</flux:button>
                <nav aria-label="{{ __('calendar.breadcrumb') }}" class="flex min-w-0 flex-wrap items-center justify-center gap-1">
                    <flux:button wire:click="showYear('{{ $year }}')" :variant="$calendarLevel === 'year' ? 'primary' : 'ghost'" size="sm">{{ $calendarYearLabel }}</flux:button>
                    @if ($calendarLevel !== 'year')
                        <span aria-hidden="true">/</span>
                        <flux:button wire:click="showMonth('{{ $month }}')" :variant="$calendarLevel === 'month' ? 'primary' : 'ghost'" size="sm">{{ $calendarMonthLabel }}</flux:button>
                    @endif
                    @if (in_array($calendarLevel, ['day', 'hour'], true))
                        <span aria-hidden="true">/</span>
                        <flux:button wire:click="showDay('{{ $day }}')" :variant="$calendarLevel === 'day' ? 'primary' : 'ghost'" size="sm">
                            {{ \App\Support\TemporalCalendar::dayLabel(\Carbon\CarbonImmutable::parse($day, $timezone), request()->user(), $timezone) }}
                        </flux:button>
                    @endif
                    @if ($calendarLevel === 'hour')
                        <span aria-hidden="true">/</span>
                        <flux:badge>{{ sprintf('%02d:00', $hour) }}</flux:badge>
                    @endif
                </nav>
                <flux:button wire:click="nextPeriod" variant="ghost" size="sm" :aria-label="__('planner.calendar.next')">›</flux:button>
            </div>

            @if ($calendarLevel === 'year')
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    @foreach ($calendarMonths as $calendarMonth)
                        <button type="button" wire:key="calendar-month-{{ $calendarMonth['date'] }}" wire:click="showMonth('{{ $calendarMonth['date'] }}')" class="rounded-xl border border-zinc-200 p-4 text-start transition hover:bg-zinc-50 focus-visible:ring-2 focus-visible:ring-zinc-500 dark:border-zinc-700 dark:hover:bg-zinc-900">
                            <span class="block font-semibold">{{ $calendarMonth['label'] }}</span>
                            <span class="block text-sm text-zinc-500">{{ trans_choice('calendar.item_count', $calendarMonth['count'], ['count' => $calendarMonth['count']]) }}</span>
                        </button>
                    @endforeach
                </div>
            @elseif ($calendarLevel === 'month')
                <div class="space-y-2 sm:hidden">
                    @foreach ($calendarDays as $calendarDay)
                        @php $items = $calendarOccurrences->get($calendarDay['date'], collect()); @endphp
                        @if ($calendarDay['inMonth'])
                            <button type="button" wire:key="calendar-mobile-{{ $calendarDay['date'] }}" wire:click="showDay('{{ $calendarDay['date'] }}')" class="flex w-full items-center justify-between gap-3 rounded-xl border border-zinc-200 p-3 text-start focus-visible:ring-2 focus-visible:ring-zinc-500 dark:border-zinc-700">
                                <span>{{ \App\Support\TemporalCalendar::dateLabel(\Carbon\CarbonImmutable::parse($calendarDay['date'], $timezone), request()->user(), $timezone) }}</span>
                                <span class="shrink-0 text-xs text-zinc-500">{{ trans_choice('calendar.item_count', $items->count(), ['count' => $items->count()]) }}</span>
                            </button>
                        @endif
                    @endforeach
                </div>
                <div class="hidden grid-cols-7 gap-px overflow-hidden rounded-xl border border-zinc-200 bg-zinc-200 dark:border-zinc-700 dark:bg-zinc-700 sm:grid">
                    @foreach (\App\Support\TemporalPreferences::weekdayOrder(request()->user()?->locale) as $weekday)
                        <div class="bg-zinc-50 px-2 py-2 text-center text-xs font-medium text-zinc-500 dark:bg-zinc-900">{{ __('planner.weekdays.'.$weekday) }}</div>
                    @endforeach
                    @foreach ($calendarDays as $calendarDay)
                        @php $items = $calendarOccurrences->get($calendarDay['date'], collect()); @endphp
                        <div wire:key="calendar-day-{{ $calendarDay['date'] }}" class="min-w-0 min-h-32 bg-white p-2 dark:bg-zinc-950 {{ $calendarDay['inMonth'] ? '' : 'opacity-50' }}">
                            <button type="button" wire:click="showDay('{{ $calendarDay['date'] }}')" class="rounded px-1 text-xs font-semibold hover:bg-zinc-100 focus-visible:ring-2 focus-visible:ring-zinc-500 dark:hover:bg-zinc-800" :aria-label="\App\Support\TemporalCalendar::dateLabel(\Carbon\CarbonImmutable::parse($calendarDay['date'], $timezone), request()->user(), $timezone)">
                                {{ $calendarDay['label'] }}
                            </button>
                            <div class="mt-2 space-y-1">
                                @foreach ($items as $occurrence)
                                    <a href="{{ route('planner.show', $occurrence->plan) }}#occurrence-{{ $occurrence->uuid }}" class="block break-words rounded-lg border border-zinc-200 px-2 py-1 text-xs hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-900">
                                        <span class="font-medium" dir="auto">{{ $occurrence->plan->title }}</span>
                                        <span class="block text-zinc-500">{{ \App\Support\TemporalCalendar::timeLabel($occurrence->scheduled_start_at, request()->user(), $timezone) }} · {{ __('planner.occurrence_status.'.$occurrence->status->value) }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @elseif ($calendarLevel === 'day')
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-zinc-50 p-3 dark:bg-zinc-900">
                    <span>{{ \App\Support\TemporalCalendar::dateLabel(\Carbon\CarbonImmutable::parse($day, $timezone), request()->user(), $timezone) }}</span>
                    <flux:button :href="route('planner.create', array_filter(['context' => $context?->uuid, 'date' => $day]))" size="sm" icon="plus">{{ __('calendar.add_to_day') }}</flux:button>
                </div>
                <div class="divide-y divide-zinc-200 overflow-hidden rounded-xl border border-zinc-200 dark:divide-zinc-800 dark:border-zinc-800">
                    @for ($slotHour = 0; $slotHour < 24; $slotHour++)
                        @php $items = $calendarHours->get($slotHour, collect()); @endphp
                        <div wire:key="calendar-hour-{{ $day }}-{{ $slotHour }}" class="flex min-w-0 flex-col bg-white dark:bg-zinc-950 sm:grid sm:grid-cols-[6rem_1fr]">
                            <button type="button" wire:click="showHour('{{ $day }}', {{ $slotHour }})" class="p-3 text-start text-sm font-medium tabular-nums text-zinc-600 hover:bg-zinc-50 focus-visible:ring-2 focus-visible:ring-zinc-500 dark:text-zinc-300 dark:hover:bg-zinc-900 sm:border-e sm:border-zinc-200 sm:dark:border-zinc-800">{{ sprintf('%02d:00', $slotHour) }}</button>
                            <div class="space-y-2 p-2">
                                @foreach ($items as $occurrence)
                                    <a href="{{ route('planner.show', $occurrence->plan) }}#occurrence-{{ $occurrence->uuid }}" class="block rounded-lg border border-zinc-200 px-3 py-2 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-900">
                                        <span dir="auto">{{ $occurrence->plan->title }}</span> · {{ \App\Support\TemporalCalendar::timeLabel($occurrence->scheduled_start_at, request()->user(), $timezone) }}
                                        <span class="text-zinc-500">· {{ __('planner.occurrence_status.'.$occurrence->status->value) }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endfor
                </div>
            @else
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-zinc-50 p-3 dark:bg-zinc-900">
                    <span class="font-medium">{{ __('calendar.hour_title', ['time' => sprintf('%02d:00', $hour)]) }}</span>
                    <div class="flex flex-wrap gap-1" aria-label="{{ __('calendar.resolution') }}">
                        @foreach ([60, 30, 15, 5, 1] as $quantum)
                            <flux:button wire:click="setSlotMinutes({{ $quantum }})" :variant="$slotMinutes === $quantum ? 'primary' : 'ghost'" size="sm">{{ __('calendar.minutes', ['count' => $quantum]) }}</flux:button>
                        @endforeach
                    </div>
                </div>
                <div class="divide-y divide-zinc-200 overflow-hidden rounded-xl border border-zinc-200 dark:divide-zinc-800 dark:border-zinc-800">
                    @foreach ($calendarSlots as $slot)
                        <div wire:key="calendar-slot-{{ $day }}-{{ $slot['time'] }}-{{ $slotMinutes }}" class="flex min-w-0 flex-col gap-2 bg-white p-3 dark:bg-zinc-950 sm:flex-row sm:items-start">
                            <span class="shrink-0 text-sm tabular-nums text-zinc-500 sm:w-16">{{ $slot['time'] }}</span>
                            <div class="min-w-0 flex-1 space-y-2">
                                @foreach ($slot['items'] as $occurrence)
                                    <a href="{{ route('planner.show', $occurrence->plan) }}#occurrence-{{ $occurrence->uuid }}" class="block rounded-lg border border-zinc-200 px-3 py-2 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-900">
                                        <span dir="auto">{{ $occurrence->plan->title }}</span> · {{ __('planner.occurrence_status.'.$occurrence->status->value) }}
                                    </a>
                                @endforeach
                            </div>
                            <flux:button :href="route('planner.create', array_filter(['context' => $context?->uuid, 'date' => $day, 'time' => $slot['time'], 'duration' => $slotMinutes]))" size="sm" variant="ghost" icon="plus" :aria-label="__('calendar.add_to_slot', ['time' => $slot['time']])">{{ __('calendar.add') }}</flux:button>
                        </div>
                    @endforeach
                </div>
            @endif
        </flux:card>
    @else
        <div class="space-y-3">
            @forelse ($occurrences as $occurrence)
                <article wire:key="planner-occurrence-{{ $occurrence->uuid }}" class="rounded-2xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-950">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <flux:badge>{{ __('planner.occurrence_status.'.$occurrence->status->value) }}</flux:badge>
                                <span class="text-xs text-zinc-500">{{ $occurrence->plan->timezone }}</span>
                            </div>
                            <a href="{{ route('planner.show', $occurrence->plan) }}#occurrence-{{ $occurrence->uuid }}" class="mt-2 block text-lg font-semibold hover:underline" dir="auto">{{ $occurrence->plan->title }}</a>
                            @if ($occurrence->plan->description)
                                <p class="mt-1 line-clamp-2 text-sm text-zinc-600 dark:text-zinc-300" dir="auto">{{ $occurrence->plan->description }}</p>
                            @endif
                        </div>
                        <div class="shrink-0 text-sm sm:text-end">
                            <div class="font-medium">{{ \App\Support\TemporalCalendar::dateLabel($occurrence->scheduled_start_at, request()->user(), $timezone) }} · {{ \App\Support\TemporalCalendar::timeLabel($occurrence->scheduled_start_at, request()->user(), $timezone) }}</div>
                            <div class="text-zinc-500">→ {{ \App\Support\TemporalCalendar::timeLabel($occurrence->scheduled_end_at, request()->user(), $timezone) }}</div>
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
                <a wire:key="planner-plan-{{ $plan->uuid }}" href="{{ route('planner.show', $plan) }}" class="rounded-xl border border-zinc-200 p-4 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-900">
                    <div class="flex items-start justify-between gap-3">
                        <div class="font-semibold" dir="auto">{{ $plan->title }}</div>
                        <flux:badge size="sm">{{ __('planner.status.'.$plan->status->value) }}</flux:badge>
                    </div>
                    <div class="mt-2 text-xs text-zinc-500">{{ $plan->timezone }}</div>
                </a>
            @empty
                <div class="md:col-span-2 xl:col-span-3"><x-app.empty-state :title="__('planner.empty.plans')" /></div>
            @endforelse
        </div>
    </flux:card>
</section>
