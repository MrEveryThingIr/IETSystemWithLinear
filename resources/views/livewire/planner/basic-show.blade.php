<section class="mx-auto max-w-4xl space-y-6">
    @php
        $category = (string) data_get($plan->metadata, 'category', '');
        $occurrences = $plan->occurrences->sortByDesc('scheduled_start_at')->values();
    @endphp

    <x-app.page-header :title="$plan->title">
        <x-slot:actions>
            <div class="flex flex-wrap gap-2">
                @if ($canManage && $plan->status === \App\PlanStatus::Active)
                    <flux:button :href="route('planner.edit', $plan)" variant="ghost" icon="pencil-square">
                        {{ __('planning_baseline.show.edit') }}
                    </flux:button>
                @endif
                <flux:button :href="route('planner.index', ['view' => 'calendar'])" variant="ghost" icon="calendar-days">
                    {{ __('planning_baseline.workspace.calendar') }}
                </flux:button>
            </div>
        </x-slot:actions>
    </x-app.page-header>


    @if ($repeatMessage !== '')
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200">
            {{ $repeatMessage }}
        </div>
    @endif

    @php
        $completedOccurrence = $occurrences
            ->first(fn ($occurrence) => $occurrence->status === \App\PlanOccurrenceStatus::Completed);
    @endphp

    @if ($canManage && $completedOccurrence)
        <x-app.tools-bar>
            <flux:button wire:click="openRepeatTool({{ $completedOccurrence->id }})" size="sm" variant="ghost">
                {{ __('planning_baseline.tools.repeat.button') }}
            </flux:button>
        </x-app.tools-bar>

        @if ($repeatToolOpen)
            <flux:card class="space-y-5">
                <div>
                    <div class="font-medium">{{ __('planning_baseline.tools.repeat.title') }}</div>
                    <div class="mt-1 text-sm text-zinc-500">
                        {{ __('planning_baseline.tools.repeat.help') }}
                    </div>
                </div>

                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach ([
                        'next_days' => 'planning_baseline.tools.repeat.modes.next_days',
                        'same_weekday' => 'planning_baseline.tools.repeat.modes.same_weekday',
                        'month' => 'planning_baseline.tools.repeat.modes.month',
                        'selected_dates' => 'planning_baseline.tools.repeat.modes.selected_dates',
                    ] as $modeValue => $labelKey)
                        <label class="cursor-pointer rounded-xl border p-3 {{ $repeatMode === $modeValue ? 'border-zinc-900 bg-zinc-50 dark:border-zinc-100 dark:bg-zinc-900' : 'border-zinc-200 dark:border-zinc-700' }}">
                            <input class="sr-only" type="radio" wire:model.live="repeatMode" value="{{ $modeValue }}">
                            <div class="text-sm font-medium">{{ __($labelKey) }}</div>
                        </label>
                    @endforeach
                </div>

                @error('repeatMode')
                    <div class="text-sm text-red-600">{{ $message }}</div>
                @enderror

                @if (in_array($repeatMode, ['next_days', 'same_weekday'], true))
                    <flux:input
                        wire:model="repeatCount"
                        type="number"
                        min="1"
                        :max="$repeatMode === 'next_days' ? 62 : 52"
                        :label="$repeatMode === 'next_days'
                            ? __('planning_baseline.tools.repeat.count_days')
                            : __('planning_baseline.tools.repeat.count_weeks')"
                    />
                @elseif ($repeatMode === 'month')
                    <flux:input
                        wire:model="repeatMonth"
                        type="month"
                        :label="__('planning_baseline.tools.repeat.month')"
                    />
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

                <div class="rounded-xl bg-zinc-50 p-3 text-sm text-zinc-600 dark:bg-zinc-900 dark:text-zinc-300">
                    {{ __('planning_baseline.tools.repeat.preserve_window') }}
                </div>

                <div class="flex flex-wrap justify-end gap-2">
                    <flux:button type="button" wire:click="closeRepeatTool" variant="ghost">
                        {{ __('studio.cancel') }}
                    </flux:button>
                    <flux:button
                        type="button"
                        wire:click="applyRepeatTool"
                        wire:loading.attr="disabled"
                        wire:target="applyRepeatTool"
                        variant="primary"
                    >
                        {{ __('planning_baseline.tools.repeat.apply') }}
                    </flux:button>
                </div>
            </flux:card>
        @endif
    @endif

    @if ($category !== '' || $plan->description)
        <flux:card class="space-y-3">
            @if ($category !== '')
                <flux:badge color="zinc">{{ $category }}</flux:badge>
            @endif
            @if ($plan->description)
                <div class="whitespace-pre-line text-sm leading-6 text-zinc-700 dark:text-zinc-300" dir="auto">{{ $plan->description }}</div>
            @endif
        </flux:card>
    @endif

    <div class="space-y-3">
        @forelse ($occurrences as $occurrence)
            @php
                $mode = $occurrence->scheduleRule?->timing_mode?->value ?? 'fixed';
                $phase = $occurrence->executionPhase();
            @endphp

            <flux:card wire:key="baseline-occurrence-{{ $occurrence->id }}" class="space-y-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="font-medium">
                            <x-app.local-date :value="$occurrence->scheduled_start_at" :show-equivalent="false" />
                        </div>
                        <div class="mt-1 text-sm text-zinc-500">
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
                </div>

                @if ($canParticipate && $plan->status === \App\PlanStatus::Active)
                    <div class="flex flex-wrap gap-2">
                        @if ($occurrence->status === \App\PlanOccurrenceStatus::Scheduled && $phase === 'ready')
                            <flux:button wire:click="startOccurrence({{ $occurrence->id }})" size="sm" variant="primary">
                                {{ __('planning_baseline.show.start') }}
                            </flux:button>
                        @elseif ($occurrence->status === \App\PlanOccurrenceStatus::Scheduled && $phase === 'passed')
                            <flux:button wire:click="skipOccurrence({{ $occurrence->id }})" size="sm" variant="ghost">
                                {{ __('planning_baseline.show.mark_skipped') }}
                            </flux:button>
                        @elseif ($occurrence->status === \App\PlanOccurrenceStatus::InProgress)
                            <flux:button wire:click="completeOccurrence({{ $occurrence->id }})" size="sm" variant="primary">
                                {{ __('planning_baseline.show.done') }}
                            </flux:button>
                        @endif
                    </div>
                @endif

                @if ($occurrence->status === \App\PlanOccurrenceStatus::Scheduled)
                    <div class="text-sm text-zinc-500">
                        @if ($phase === 'upcoming')
                            {{ __('planning_baseline.show.upcoming') }}
                        @elseif ($phase === 'ready')
                            {{ __('planning_baseline.show.ready') }}
                        @else
                            {{ __('planning_baseline.show.passed') }}
                        @endif
                    </div>
                @elseif ($occurrence->status === \App\PlanOccurrenceStatus::Completed)
                    <div class="text-sm text-zinc-500">
                        {{ __('planning_baseline.show.completed_at') }}
                        <x-app.local-time :value="$occurrence->actual_end_at" />
                    </div>
                @endif
            </flux:card>
        @empty
            <x-app.empty-state :title="__('planning_baseline.show.no_occurrences')" />
        @endforelse
    </div>

    <p class="text-xs text-zinc-500">
        {{ __('planning_baseline.show.done_meaning') }}
    </p>
</section>
