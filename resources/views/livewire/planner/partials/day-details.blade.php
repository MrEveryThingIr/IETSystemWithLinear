@php
    $selectedDayCreateParams = [
        'date' => $selectedDate,
        'time' => '09:00',
    ];

    if ($context) {
        $selectedDayCreateParams['context'] = $context->uuid;
    }

    $selectedDayCreateUrl = route('planner.create', $selectedDayCreateParams);
@endphp

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

        <flux:button :href="$selectedDayCreateUrl" variant="primary" icon="plus">
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
                $hourCreateParams = [
                    'date' => $selectedDate,
                    'time' => $hourValue,
                ];

                if ($context) {
                    $hourCreateParams['context'] = $context->uuid;
                }

                $hourCreateUrl = route('planner.create', $hourCreateParams);
            @endphp

            <div class="grid min-h-16 grid-cols-[4.5rem_minmax(0,1fr)] border-b border-zinc-100 last:border-b-0 dark:border-zinc-800">
                <div class="border-e border-zinc-100 px-3 py-3 text-xs font-medium text-zinc-500 dark:border-zinc-800">
                    {{ $hourValue }}
                </div>

                <div class="space-y-2 p-2">
                    @forelse ($hourItems as $occurrence)
                        @php
                            $windowState = $occurrence->windowState();
                        @endphp

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
                            href="{{ $hourCreateUrl }}"
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
