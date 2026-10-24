<section class="mx-auto max-w-7xl space-y-6">
    <x-app.flash-message />

    <x-app.page-header :title="__('planner.title')" :description="__('planner.help')">
        <x-slot:actions>
            <x-app.popover>
                <x-slot:trigger><flux:button variant="ghost" icon="tag" data-labels-button>{{ __('planner.labels.manage') }}</flux:button></x-slot:trigger>
                <livewire:planner.labels-manager />
            </x-app.popover>
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
                        <flux:badge>{{ str_pad((string) $hour, 2, '0', STR_PAD_LEFT) }}:00</flux:badge>
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
                            <span class="block font-semibold">{{ $calendarMonth['label'] }}</span>
                            <span class="mt-1 block text-sm text-zinc-500">{{ trans_choice('planner.calendar.item_count', $calendarMonth['count'], ['count' => $calendarMonth['count']]) }}</span>
                        </button>
                    @endforeach
                </div>
            @elseif ($calendarLevel === 'day')
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-zinc-50 p-3 dark:bg-zinc-900">
                    <span class="font-medium"><x-app.local-date :value="$day" /></span>
                    <flux:button :href="route('planner.create', array_filter(['context' => $context?->uuid, 'date' => $day]))" size="sm" icon="plus">
                        {{ __('planner.calendar.add_to_day') }}
                    </flux:button>
                </div>

                <div class="divide-y divide-zinc-200 overflow-hidden rounded-xl border border-zinc-200 dark:divide-zinc-800 dark:border-zinc-800">
                    @for ($hour = 0; $hour < 24; $hour++)
                        @php
                            $items = $calendarHours->get($hour, collect());
                            $itemCount = $items->count();
                        @endphp
                        <div wire:key="calendar-hour-{{ $day }}-{{ $hour }}" class="grid h-16 grid-cols-[5rem_1fr] bg-white dark:bg-zinc-950">
                            <button
                                type="button"
                                wire:click="showHour('{{ $day }}', {{ $hour }})"
                                class="border-e border-zinc-200 px-3 py-3 text-start text-xs font-medium tabular-nums text-zinc-500 transition hover:bg-zinc-50 hover:text-zinc-900 dark:border-zinc-800 dark:hover:bg-zinc-900 dark:hover:text-white"
                            >
                                {{ str_pad((string) $hour, 2, '0', STR_PAD_LEFT) }}:00
                            </button>
                            <button
                                type="button"
                                wire:click="showHour('{{ $day }}', {{ $hour }})"
                                class="min-w-0 px-3 py-2 text-start transition hover:bg-zinc-50 dark:hover:bg-zinc-900"
                            >
                                @if ($itemCount > 0)
                                    <span class="block text-sm font-medium">{{ trans_choice('planner.calendar.item_count', $itemCount, ['count' => $itemCount]) }}</span>
                                    <span class="block text-xs text-zinc-500">{{ __('planner.calendar.drill_down') }}</span>
                                @else
                                    <span class="text-xs text-zinc-400">{{ __('planner.calendar.empty_bucket') }}</span>
                                @endif
                            </button>
                        </div>
                    @endfor
                </div>
            @elseif ($calendarLevel === 'hour')
                <div class="space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-zinc-50 p-3 dark:bg-zinc-900">
                        <div>
                            <div class="font-medium">
                                {{ __('planner.calendar.hour_title', ['time' => str_pad((string) $hour, 2, '0', STR_PAD_LEFT).':00']) }}
                            </div>
                            <div class="mt-1 text-xs text-zinc-500">{{ __('planner.calendar.quantum_help') }}</div>
                        </div>

                        <div class="flex flex-wrap items-center gap-1">
                            @foreach ([60, 30, 15, 5, 1] as $quantum)
                                <flux:button
                                    wire:click="setSlotMinutes({{ $quantum }})"
                                    :variant="$slotMinutes === $quantum ? 'primary' : 'ghost'"
                                    size="sm"
                                >
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
                            <div wire:key="calendar-slot-{{ $day }}-{{ $slotTime }}-{{ $slotMinutes }}" class="grid h-14 grid-cols-[6rem_1fr_auto] items-stretch bg-white dark:bg-zinc-950">
                                <div class="border-e border-zinc-200 px-3 py-3 text-xs font-medium tabular-nums text-zinc-500 dark:border-zinc-800">
                                    {{ $slotTime }}
                                </div>

                                <button
                                    type="button"
                                    wire:click="selectSlot({{ $slotMinute }})"
                                    class="min-w-0 px-3 py-2 text-start transition hover:bg-zinc-50 dark:hover:bg-zinc-900 {{ $selectedMinute === $slotMinute ? 'bg-zinc-50 dark:bg-zinc-900' : '' }}"
                                >
                                    @if ($itemCount > 0)
                                        <span class="text-sm font-medium">{{ trans_choice('planner.calendar.item_count', $itemCount, ['count' => $itemCount]) }}</span>
                                        <span class="ms-2 text-xs text-zinc-500">{{ __('planner.calendar.open_items') }}</span>
                                    @else
                                        <span class="text-xs text-zinc-400">{{ __('planner.calendar.empty_bucket') }}</span>
                                    @endif
                                </button>

                                <div class="p-2">
                                    <flux:button
                                        :href="route('planner.create', array_filter([
                                            'context' => $context?->uuid,
                                            'date' => $day,
                                            'time' => $slotTime,
                                            'duration' => $slotMinutes,
                                        ]))"
                                        size="sm"
                                        variant="ghost"
                                        icon="plus"
                                        :aria-label="__('planner.calendar.add_to_slot', ['time' => $slotTime])"
                                    >
                                        {{ __('planner.calendar.add') }}
                                    </flux:button>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @php
                        $selectedSlot = $selectedMinute >= 0
                            ? $calendarSlots->first(fn ($slot) => (int) $slot['start']->format('i') === $selectedMinute)
                            : null;
                    @endphp

                    @if ($selectedSlot)
                        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <div class="font-medium">{{ __('planner.calendar.slot_details', ['time' => $selectedSlot['start']->format('H:i')]) }}</div>
                                    <div class="mt-1 text-xs text-zinc-500">{{ trans_choice('planner.calendar.item_count', $selectedSlotItems->count(), ['count' => $selectedSlotItems->count()]) }}</div>
                                </div>
                            </div>

                            <div class="mt-3 max-h-96 space-y-2 overflow-y-auto pe-1">
                                @forelse ($selectedSlotItems as $occurrence)
                                    <a
                                        href="{{ route('planner.show', $occurrence->plan) }}#occurrence-{{ $occurrence->uuid }}"
                                        class="block rounded-lg border border-zinc-200 px-3 py-2 text-sm hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-900"
                                    >
                                        <div class="font-medium" dir="auto">{{ $occurrence->plan->title }}</div>
                                        <div class="mt-1 text-xs text-zinc-500">
                                            <x-app.local-time :value="$occurrence->scheduled_start_at" />
                                            →
                                            <x-app.local-time :value="$occurrence->scheduled_end_at" />
                                            · {{ __('planner.occurrence_phase.'.$occurrence->executionPhase()) }}
                                        </div>
                                    </a>
                                @empty
                                    <p class="text-sm text-zinc-500">{{ __('planner.calendar.empty_bucket') }}</p>
                                @endforelse
                            </div>
                        </div>
                    @endif
                </div>
            @else
                <div class="grid grid-cols-7 gap-px overflow-hidden rounded-xl border border-zinc-200 bg-zinc-200 dark:border-zinc-700 dark:bg-zinc-700">
                    @foreach (\App\Support\TemporalPreferences::weekdayOrder(request()->user()?->locale) as $weekday)
                        <div class="bg-zinc-50 px-2 py-2 text-center text-xs font-medium text-zinc-500 dark:bg-zinc-900">
                            {{ __('planner.weekdays.'.$weekday) }}
                        </div>
                    @endforeach

                    @foreach ($calendarDays as $calendarDay)
                        @php
                            $dateKey = $calendarDay['key'];
                            $items = $calendarOccurrences->get($dateKey, collect());
                            $itemCount = $items->count();
                            $phaseCounts = $items->countBy(fn ($occurrence) => $occurrence->executionPhase());
                            $inMonth = $calendarDay['in_month'];
                        @endphp
                        <button
                            type="button"
                            wire:key="calendar-day-{{ $dateKey }}"
                            wire:click="showDay('{{ $dateKey }}')"
                            class="h-28 overflow-hidden bg-white p-2 text-start transition hover:bg-zinc-50 dark:bg-zinc-950 dark:hover:bg-zinc-900 {{ $inMonth ? '' : 'opacity-50' }}"
                            aria-label="{{ __('planner.calendar.open_day', ['date' => $dateKey]) }}"
                        >
                            <span class="block text-xs font-semibold text-zinc-500">{{ $calendarDay['label'] }}</span>

                            @if ($itemCount > 0)
                                <span class="mt-2 flex items-center gap-1.5 text-sm font-semibold">
                                    {{ trans_choice('planner.calendar.item_count', $itemCount, ['count' => $itemCount]) }}
                                    <span class="flex gap-0.5" aria-hidden="true">
                                        @foreach ($items->map(fn ($occurrence) => $appearance->of($occurrence->plan)['color'])->unique()->take(4) as $dot)
                                            <span class="size-2 rounded-full" style="background: {{ $dot }}"></span>
                                        @endforeach
                                    </span>
                                </span>
                                <span class="mt-1 block space-y-0.5 text-[11px] leading-4 text-zinc-500">
                                    @foreach ($phaseCounts->take(3) as $phase => $count)
                                        <span class="block">{{ __('planner.occurrence_phase.'.$phase) }} · {{ $count }}</span>
                                    @endforeach
                                </span>
                            @else
                                <span class="mt-2 block text-xs text-zinc-400">{{ __('planner.calendar.empty_bucket') }}</span>
                            @endif
                        </button>
                    @endforeach
                </div>
            @endif
        </flux:card>
    @else
        {{-- One card per plan: a repeating plan is shown once with its times, not as a long list. --}}
        @php
            $series = $occurrences->groupBy('plan_id')->sortBy(fn ($items) => $items->first()->scheduled_start_at->getTimestamp());
        @endphp
        <div class="space-y-3" data-planner-series>
            @forelse ($series as $planId => $items)
                @php
                    $plan = $items->first()->plan;
                    $look = $appearance->of($plan);
                    $expanded = in_array($plan->uuid, $expandedPlans, true);
                    $visible = $expanded ? $items : $items->take($view === 'today' ? 6 : 4);
                    $summary = \App\Support\Planner\PlanSeries::summary($plan);
                @endphp
                <article wire:key="planner-series-{{ $plan->uuid }}" class="overflow-hidden rounded-2xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-950" style="border-inline-start: 4px solid {{ $look['color'] }}" data-plan-series="{{ $plan->uuid }}">
                    <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="flex min-w-0 items-start gap-3">
                            <span class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg text-white" style="background: {{ $look['color'] }}"><flux:icon :name="$look['icon']" class="size-4" /></span>
                            <div class="min-w-0">
                                <a href="{{ route('planner.show', $plan) }}" wire:navigate class="block font-semibold hover:underline" dir="auto">{{ $plan->title }}</a>
                                <div class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-zinc-500">
                                    @if ($look['label'])<span class="inline-flex items-center gap-1"><span class="size-2 rounded-full" style="background: {{ $look['label']->color }}"></span><span dir="auto">{{ $look['label']->name }}</span></span>@endif
                                    @if ($summary !== '')<span>{{ $summary }}</span>@endif
                                    @if ($view === 'today' && $items->count() > 1)<span class="font-medium text-zinc-700 dark:text-zinc-300">{{ __('planner.series.times_today', ['count' => $items->count()]) }}</span>@endif
                                </div>
                            </div>
                        </div>
                        <div class="shrink-0 text-sm sm:text-end">
                            <div class="font-medium"><x-app.local-datetime :value="$items->first()->scheduled_start_at" /></div>
                            <div class="text-xs text-zinc-500">→ <x-app.local-time :value="$items->first()->scheduled_end_at" /> · {{ __('planner.occurrence_phase.'.$items->first()->executionPhase()) }}</div>
                        </div>
                    </div>
                    @if ($items->count() > 1)
                        <div class="flex flex-wrap gap-1.5 border-t border-zinc-100 px-4 py-2.5 dark:border-zinc-800">
                            @foreach ($visible->skip(1) as $occurrence)
                                <a href="{{ route('planner.show', $plan) }}#occurrence-{{ $occurrence->uuid }}" wire:navigate wire:key="series-occ-{{ $occurrence->uuid }}" class="rounded-lg bg-zinc-100 px-2 py-1 text-xs hover:bg-zinc-200 dark:bg-zinc-900 dark:hover:bg-zinc-800">
                                    @if ($view === 'today')<x-app.local-time :value="$occurrence->scheduled_start_at" />@else<x-app.local-datetime :value="$occurrence->scheduled_start_at" />@endif
                                </a>
                            @endforeach
                            @if ($items->count() > $visible->count())
                                <button type="button" wire:click="toggleSeries('{{ $plan->uuid }}')" class="rounded-lg px-2 py-1 text-xs font-medium text-zinc-600 underline-offset-4 hover:underline dark:text-zinc-300" data-series-more>{{ __('planner.series.more_times', ['count' => $items->count() - $visible->count()]) }}</button>
                            @elseif ($expanded)
                                <button type="button" wire:click="toggleSeries('{{ $plan->uuid }}')" class="rounded-lg px-2 py-1 text-xs text-zinc-500 hover:underline">{{ __('planner.series.show_less') }}</button>
                            @endif
                        </div>
                    @endif
                </article>
            @empty
                <x-app.empty-state
                    :title="$view === 'today' ? __('planner.empty.today') : __('planner.empty.list')"
                />
            @endforelse
        </div>
    @endif

    @if ($meetingsWaiting->isNotEmpty() || $meetingsOrganized->isNotEmpty())
        <flux:card class="space-y-4" data-planner-meetings>
            <flux:heading size="lg">{{ __('planner.meetings.section') }}</flux:heading>
            <div class="grid gap-3 md:grid-cols-2">
                @foreach ($meetingsWaiting as $waiting)
                    <a href="{{ route('planner.meetings.show', $waiting->meeting) }}" wire:navigate class="rounded-xl border border-violet-300 bg-violet-50 p-3 hover:bg-violet-100 dark:border-violet-800 dark:bg-violet-950" data-meeting-waiting>
                        <div class="text-xs font-medium text-violet-700 dark:text-violet-300">{{ __('planner.meetings.waiting_for_you') }}</div>
                        <div class="font-semibold" dir="auto">{{ $waiting->meeting->title }}</div>
                        <div class="text-xs text-zinc-600 dark:text-zinc-300">{{ __('planner.meetings.organized_by', ['name' => $waiting->meeting->organizer?->user?->username]) }}</div>
                    </a>
                @endforeach
                @foreach ($meetingsOrganized as $organized)
                    <a href="{{ route('planner.meetings.show', $organized) }}" wire:navigate class="rounded-xl border border-zinc-200 p-3 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-900" data-meeting-organized>
                        <div class="text-xs font-medium text-zinc-500">{{ __('planner.meetings.organized') }} · {{ __('planner.meetings.status.open') }}</div>
                        <div class="font-semibold" dir="auto">{{ $organized->title }}</div>
                        <div class="text-xs text-zinc-500">{{ $organized->answered_count }}/{{ $organized->invitees_count }} {{ __('planner.meetings.answers_title') }}</div>
                    </a>
                @endforeach
            </div>
        </flux:card>
    @endif

    <flux:card class="space-y-4">
        <div class="flex items-center justify-between gap-3">
            <flux:heading size="lg">{{ __('planner.plan.schedule_rules') }}</flux:heading>
            <span class="text-sm text-zinc-500">{{ $plans->count() }}</span>
        </div>

        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($plans as $plan)
                @php $look = $appearance->of($plan); $summary = \App\Support\Planner\PlanSeries::summary($plan); @endphp
                <a
                    wire:key="planner-plan-{{ $plan->uuid }}"
                    href="{{ route('planner.show', $plan) }}"
                    wire:navigate
                    class="rounded-xl border border-zinc-200 p-4 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-900"
                    style="border-inline-start: 4px solid {{ $look['color'] }}"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="font-semibold" dir="auto">{{ $plan->title }}</div>
                        <flux:badge size="sm">{{ __('planner.status.'.$plan->status->value) }}</flux:badge>
                    </div>
                    <div class="mt-2 flex flex-wrap items-center gap-x-2 text-xs text-zinc-500">
                        @if ($look['kind'])<span>{{ __('planner.kinds.'.$look['kind'].'.name') }}</span>@endif
                        @if ($look['label'])<span dir="auto">· {{ $look['label']->name }}</span>@endif
                        @if ($summary !== '')<span>· {{ $summary }}</span>@endif
                    </div>
                </a>
            @empty
                <div class="md:col-span-2 xl:col-span-3">
                    <x-app.empty-state :title="__('planner.empty.plans')" />
                </div>
            @endforelse
        </div>
    </flux:card>
</section>
