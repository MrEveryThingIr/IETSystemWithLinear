<x-app.planner-studio-shell :source="$sourceOccurrence?->uuid">
    <section class="min-h-[28rem] space-y-6">
        <x-app.page-header
            :title="__('planning_baseline.tools.title')"
            :description="__('planning_baseline.sidebar.help')"
        />

        <div class="rounded-2xl border border-dashed border-zinc-300 p-8 text-center dark:border-zinc-700">
            <div class="font-medium">{{ __('planning_baseline.tools.repeat.sidebar') }}</div>
            <div class="mt-1 text-sm text-zinc-500">{{ __('planning_baseline.tools.repeat.sidebar_help') }}</div>
        </div>
    </section>

    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/35 p-4 backdrop-blur-[1px]" role="presentation">
        <div
            class="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-2xl border border-zinc-200 bg-white shadow-2xl dark:border-zinc-800 dark:bg-zinc-950"
            role="dialog"
            aria-modal="true"
            aria-labelledby="repeat-window-title"
        >
            <div class="sticky top-0 z-10 flex items-start justify-between gap-4 border-b border-zinc-200 bg-white px-5 py-4 dark:border-zinc-800 dark:bg-zinc-950">
                <div>
                    <h2 id="repeat-window-title" class="text-lg font-semibold">
                        {{ __('planning_baseline.tools.repeat.title') }}
                    </h2>
                    <p class="mt-1 text-sm leading-6 text-zinc-500">
                        {{ __('planning_baseline.tools.repeat.help') }}
                    </p>
                </div>

                <a
                    href="{{ $cancelUrl }}"
                    class="inline-flex size-9 shrink-0 items-center justify-center rounded-lg text-xl text-zinc-500 hover:bg-zinc-100 dark:hover:bg-zinc-900"
                    aria-label="{{ __('studio.cancel') }}"
                >
                    ×
                </a>
            </div>

            <div class="space-y-5 p-5">
                @if ($message !== '')
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">
                        {{ $message }}
                    </div>
                @endif

                <div>
                    <label class="mb-1 block text-sm font-medium">{{ __('planning_baseline.tools.repeat.source') }}</label>
                    <select
                        wire:model.live="sourceUuid"
                        class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950"
                    >
                        <option value="">{{ __('planning_baseline.tools.repeat.choose_source') }}</option>
                        @foreach ($sources as $source)
                            <option value="{{ $source->uuid }}">
                                {{ $source->plan->title }} — {{ $source->local_date->format('Y-m-d') }}
                                @if (($source->scheduleRule?->timing_mode ?? \App\PlanTimingMode::Fixed) === \App\PlanTimingMode::Fixed)
                                    {{ substr((string) $source->scheduleRule->start_time, 0, 5) }}
                                @endif
                            </option>
                        @endforeach
                    </select>
                    @error('sourceUuid')
                        <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
                    @enderror
                </div>

                @if ($sourceOccurrence)
                    <div class="rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="font-medium" dir="auto">{{ $sourceOccurrence->plan->title }}</div>
                        <div class="mt-1 text-sm text-zinc-500">
                            <x-app.local-date :value="$sourceOccurrence->scheduled_start_at" :show-equivalent="false" />
                            ·
                            @if (($sourceOccurrence->scheduleRule?->timing_mode ?? \App\PlanTimingMode::Fixed) === \App\PlanTimingMode::FlexibleDay)
                                {{ __('planning_baseline.timing.flexible_day_short') }}
                            @else
                                <x-app.local-time :value="$sourceOccurrence->scheduled_start_at" />
                                –
                                <x-app.local-time :value="$sourceOccurrence->scheduled_end_at" />
                            @endif
                        </div>
                    </div>

                    <div>
                        <div class="mb-2 text-sm font-medium">{{ __('planning_baseline.tools.repeat.pattern') }}</div>
                        <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                            @foreach ([
                                'next_days' => 'planning_baseline.tools.repeat.modes.next_days',
                                'same_weekday' => 'planning_baseline.tools.repeat.modes.same_weekday',
                                'same_monthday' => 'planning_baseline.tools.repeat.modes.same_monthday',
                                'same_yearday' => 'planning_baseline.tools.repeat.modes.same_yearday',
                                'month' => 'planning_baseline.tools.repeat.modes.month',
                                'selected_dates' => 'planning_baseline.tools.repeat.modes.selected_dates',
                            ] as $modeValue => $labelKey)
                                <label class="cursor-pointer rounded-xl border p-3 {{ $repeatMode === $modeValue ? 'border-zinc-900 bg-zinc-50 dark:border-zinc-100 dark:bg-zinc-900' : 'border-zinc-200 dark:border-zinc-700' }}">
                                    <input class="sr-only" type="radio" wire:model.live="repeatMode" value="{{ $modeValue }}">
                                    <div class="text-sm font-medium">{{ __($labelKey) }}</div>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    @if (in_array($repeatMode, ['next_days', 'same_weekday', 'same_monthday', 'same_yearday'], true))
                        <flux:input
                            wire:model="repeatCount"
                            type="number"
                            min="1"
                            :max="$repeatMode === 'same_yearday' ? 20 : ($repeatMode === 'same_monthday' ? 36 : ($repeatMode === 'same_weekday' ? 52 : 62))"
                            :label="__('planning_baseline.tools.repeat.count_instances')"
                        />
                    @elseif ($repeatMode === 'month')
                        <flux:input wire:model="repeatMonth" type="month" :label="__('planning_baseline.tools.repeat.month')" />
                    @else
                        <div class="space-y-3">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
                                <div class="flex-1">
                                    <x-app.calendar-date-input model="repeatDate" :label="__('planning_baseline.tools.repeat.date')" />
                                </div>
                                <flux:button type="button" wire:click="addRepeatDate" variant="ghost">
                                    {{ __('planning_baseline.tools.repeat.add_date') }}
                                </flux:button>
                            </div>

                            @error('repeatDate')
                                <div class="text-sm text-red-600">{{ $message }}</div>
                            @enderror
                            @error('repeatDates')
                                <div class="text-sm text-red-600">{{ $message }}</div>
                            @enderror

                            @if ($repeatDates !== [])
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($repeatDates as $date)
                                        <button
                                            type="button"
                                            wire:click="removeRepeatDate('{{ $date }}')"
                                            class="rounded-full border border-zinc-200 px-3 py-1 text-xs hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-900"
                                        >
                                            {{ $date }} ×
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif

                    <div class="rounded-xl bg-zinc-50 p-4 text-sm leading-6 text-zinc-600 dark:bg-zinc-900 dark:text-zinc-300">
                        {{ __('planning_baseline.tools.repeat.fractal_help') }}
                    </div>
                @endif
            </div>

            <div class="sticky bottom-0 flex flex-wrap justify-end gap-2 border-t border-zinc-200 bg-white px-5 py-4 dark:border-zinc-800 dark:bg-zinc-950">
                <flux:button :href="$cancelUrl" variant="ghost">
                    {{ __('studio.cancel') }}
                </flux:button>

                <flux:button
                    type="button"
                    wire:click="apply"
                    wire:loading.attr="disabled"
                    wire:target="apply"
                    variant="primary"
                    :disabled="$sourceOccurrence === null"
                >
                    {{ __('planning_baseline.tools.repeat.apply') }}
                </flux:button>
            </div>
        </div>
    </div>
</x-app.planner-studio-shell>
