@php
    $category = (string) data_get($plan->metadata, 'category', '');
    $occurrences = $plan->occurrences->sortByDesc('scheduled_start_at')->values();
    $repeatSource = $occurrences->first(fn ($occurrence) =>
        $occurrence->status !== \App\PlanOccurrenceStatus::Cancelled
        && $occurrence->scheduleRule?->frequency === \App\PlanScheduleFrequency::Once
    );
@endphp

<x-app.planner-studio-shell :source="$repeatSource?->uuid">
<section class="mx-auto max-w-4xl space-y-6">

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

    @if ($category !== '' || $plan->description || $plan->attention_mode)
        <flux:card class="space-y-3">
            <div class="flex flex-wrap gap-2">
                @if ($category !== '')
                    <flux:badge color="zinc">{{ $category }}</flux:badge>
                @endif
                <flux:badge color="zinc">
                    {{ __('planning_baseline.attention.'.$plan->attention_mode->value) }}
                </flux:badge>
            </div>
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
</x-app.planner-studio-shell>
