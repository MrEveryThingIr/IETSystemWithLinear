{{--
    Visual time picker (resources/js/planner-picker.js). Writes startsOn,
    startTime, durationMinutes and selectedDays on the Livewire component.
    slot-mode: adds each chosen time as an option (meetings) via addSlotsFromPicker().
--}}
@props(['picker', 'busy' => [], 'slotMode' => false])

@php
    $config = [
        'horizons' => $picker['horizons'],
        'days' => $picker['days'],
        'today' => $picker['today'],
        'busy' => $busy,
        'slotMode' => $slotMode,
        'locale' => \App\Support\Localization::intlLocale(auth()->user()?->locale),
        'labels' => [
            'hours' => __('planner.picker.hours'),
            'minutes' => __('planner.picker.minutes'),
            'separator' => __('planner.picker.separator'),
        ],
    ];
@endphp

<div x-data="plannerTimePicker(@js($config))" class="space-y-4" data-time-picker wire:ignore>
    {{-- 1. Which span of time --}}
    <div class="-mx-1 flex gap-1.5 overflow-x-auto px-1 pb-1" role="tablist" aria-label="{{ __('planner.picker.when') }}">
        @foreach (array_keys($picker['horizons']) as $horizon)
            @continue($picker['horizons'][$horizon]['days'] === [])
            <button type="button" role="tab"
                x-on:click="chooseHorizon('{{ $horizon }}')"
                x-bind:aria-selected="horizon === '{{ $horizon }}'"
                x-bind:class="horizon === '{{ $horizon }}' ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'bg-zinc-100 text-zinc-700 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-200'"
                class="shrink-0 rounded-full px-3.5 py-1.5 text-sm font-medium transition"
                data-horizon="{{ $horizon }}"
            >{{ __('planner.picker.horizons.'.$horizon) }}</button>
        @endforeach
    </div>

    {{-- 2. A week of a month --}}
    <template x-if="weeks().length > 1">
        <div class="flex flex-wrap gap-1.5" aria-label="{{ __('planner.picker.weeks') }}">
            <template x-for="(week, index) in weeks()" :key="index">
                <button type="button" x-on:click="chooseWeek(index)"
                    x-bind:class="week === index ? 'border-zinc-900 bg-zinc-900 text-white dark:border-white dark:bg-white dark:text-zinc-900' : 'border-zinc-200 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800'"
                    class="rounded-lg border px-2.5 py-1 text-xs"
                    x-text="`${days[week[0]].label} – ${days[week[week.length - 1]].label}`"></button>
            </template>
        </div>
    </template>

    {{-- 3. The day (or several days) --}}
    <div class="space-y-2">
        <div class="flex items-center justify-between gap-2">
            <span class="text-xs font-medium text-zinc-500">{{ __('planner.picker.days') }}</span>
            @unless ($slotMode)
                <button type="button" x-on:click="toggleMulti()" class="text-xs font-medium underline-offset-4 hover:underline" x-bind:class="multi ? 'text-violet-700 dark:text-violet-300' : 'text-zinc-500'" data-picker-multi>
                    <span x-text="multi ? @js(__('planner.picker.several_days_on')) : @js(__('planner.picker.several_days'))"></span>
                </button>
            @endunless
        </div>
        <div class="grid grid-cols-4 gap-1.5 sm:grid-cols-7">
            <template x-for="date in horizonDays()" :key="date">
                <button type="button" x-on:click="toggleDay(date)"
                    x-bind:aria-pressed="selected.includes(date)"
                    x-bind:class="selected.includes(date) ? 'border-violet-600 bg-violet-600 text-white' : 'border-zinc-200 hover:border-zinc-400 dark:border-zinc-700'"
                    class="flex flex-col items-center rounded-xl border px-1 py-2 text-center transition"
                    x-bind:data-day="date">
                    <span class="text-[0.7rem] font-medium opacity-80" x-text="days[date].short"></span>
                    <span class="text-sm font-semibold" x-text="days[date].label"></span>
                    <span class="mt-0.5 flex h-1.5 gap-0.5" aria-hidden="true">
                        <template x-for="(item, i) in busyFor(date).slice(0, 4)" :key="i"><span class="size-1.5 rounded-full" x-bind:style="`background:${item[3]}`"></span></template>
                    </span>
                </button>
            </template>
        </div>
    </div>

    {{-- 4. Drag on the day's timeline from start to end --}}
    <div class="space-y-2">
        <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-zinc-500">
            <span>{{ __('planner.picker.drag_help') }}</span>
            <span class="flex items-center gap-1">
                <template x-for="minutes in presets" :key="minutes">
                    <button type="button" x-on:click="preset(minutes)" class="rounded-md border border-zinc-200 px-1.5 py-0.5 hover:bg-zinc-100 dark:border-zinc-700 dark:hover:bg-zinc-800"
                        x-bind:class="end - start === minutes ? 'border-violet-500 text-violet-700 dark:text-violet-300' : ''"
                        x-text="minutes < 60 ? @js(__('planner.picker.minutes')).replace(':count', hourLabel(minutes)) : @js(__('planner.picker.hours')).replace(':count', hourLabel(minutes / 60))"></button>
                </template>
            </span>
        </div>
        <div x-ref="scroller" class="overflow-x-auto rounded-xl border border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900" dir="ltr">
            <div x-ref="track" class="relative h-24 min-w-[56rem] cursor-crosshair touch-none select-none"
                x-on:pointerdown="pointerDown($event)" x-on:pointermove="pointerMove($event)" x-on:pointerup="pointerUp()" x-on:pointercancel="pointerUp()"
                data-timeline>
                <template x-for="hour in 24" :key="hour">
                    <div class="pointer-events-none absolute inset-y-0 border-s border-zinc-200 dark:border-zinc-800" x-bind:style="`left:${((hour - 1) / 24) * 100}%`">
                        <span class="absolute start-1 top-1 text-[0.65rem] text-zinc-400" x-text="hourLabel(hour - 1)"></span>
                    </div>
                </template>
                <template x-for="(item, i) in busyFor(selected[0])" :key="'b' + i">
                    <div class="pointer-events-none absolute top-6 h-6 overflow-hidden rounded-md px-1 text-[0.65rem] leading-6 text-white opacity-70" x-bind:style="span(item[0], item[1]) + `;background:${item[3]}`" x-bind:title="item[2]" x-text="item[2]"></div>
                </template>
                <div class="absolute bottom-2 top-12 rounded-lg border-2 border-violet-600 bg-violet-500/25" x-bind:style="span(start, end)" data-selection>
                    <div data-handle="start" class="absolute -start-1.5 inset-y-0 w-3 cursor-ew-resize rounded bg-violet-600"></div>
                    <div data-handle="end" class="absolute -end-1.5 inset-y-0 w-3 cursor-ew-resize rounded bg-violet-600"></div>
                </div>
            </div>
        </div>
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="text-sm font-medium" dir="auto" x-text="summary()" data-picker-summary></div>
            <div class="flex items-center gap-1 text-xs" dir="ltr">
                <button type="button" x-on:click="nudge('start', -step)" class="rounded border border-zinc-200 px-1.5 dark:border-zinc-700" aria-label="{{ __('planner.picker.earlier_start') }}">−</button>
                <span x-text="clock(start)"></span>
                <button type="button" x-on:click="nudge('start', step)" class="rounded border border-zinc-200 px-1.5 dark:border-zinc-700" aria-label="{{ __('planner.picker.later_start') }}">+</button>
                <span class="mx-1">→</span>
                <button type="button" x-on:click="nudge('end', -step)" class="rounded border border-zinc-200 px-1.5 dark:border-zinc-700" aria-label="{{ __('planner.picker.earlier_end') }}">−</button>
                <span x-text="clock(end)"></span>
                <button type="button" x-on:click="nudge('end', step)" class="rounded border border-zinc-200 px-1.5 dark:border-zinc-700" aria-label="{{ __('planner.picker.later_end') }}">+</button>
            </div>
        </div>
        <p x-show="conflicts()" x-cloak class="text-xs text-amber-700 dark:text-amber-400">{{ __('planner.picker.conflict') }}</p>
        @if ($slotMode)
            <flux:button type="button" x-on:click="addSlot()" size="sm" icon="plus" data-picker-add-slot>{{ __('planner.picker.add_option') }}</flux:button>
        @endif
    </div>
</div>
