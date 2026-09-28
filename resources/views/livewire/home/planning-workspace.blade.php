<section class="mx-auto max-w-6xl space-y-6">
    <x-app.page-header :title="__('planning_baseline.workspace.title')" :description="__('planning_baseline.workspace.help')">
        <x-slot:actions>
            <div class="flex flex-wrap gap-2">
                <flux:button :href="route('planner.create')" variant="primary" icon="plus">
                    {{ __('planning_baseline.workspace.new_item') }}
                </flux:button>
                <flux:button :href="route('planner.index', ['view' => 'calendar'])" variant="ghost" icon="calendar-days">
                    {{ __('planning_baseline.workspace.calendar') }}
                </flux:button>
            </div>
        </x-slot:actions>
    </x-app.page-header>

    @if ($runningOccurrences->isNotEmpty())
        <flux:card class="space-y-3">
            <div>
                <flux:heading size="lg">{{ __('planning_baseline.workspace.running') }}</flux:heading>
                <flux:text>{{ __('planning_baseline.workspace.running_help') }}</flux:text>
            </div>
            <div class="grid gap-3 md:grid-cols-2">
                @foreach ($runningOccurrences as $occurrence)
                    <a href="{{ route('planner.show', $occurrence->plan) }}" class="rounded-xl border border-zinc-200 p-4 hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-900">
                        <div class="font-semibold" dir="auto">{{ $occurrence->plan->title }}</div>
                        <div class="mt-1 text-sm text-zinc-500">
                            {{ __('planning_baseline.workspace.started') }}
                            <x-app.local-time :value="$occurrence->actual_start_at" />
                        </div>
                    </a>
                @endforeach
            </div>
        </flux:card>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <flux:card class="space-y-4">
            <div>
                <flux:heading size="lg">{{ __('planning_baseline.workspace.today') }}</flux:heading>
                <flux:text>{{ __('planning_baseline.workspace.today_help') }}</flux:text>
            </div>

            <div class="space-y-2">
                @forelse ($todayOccurrences as $occurrence)
                    <a href="{{ route('planner.show', $occurrence->plan) }}" class="flex items-center justify-between gap-4 rounded-xl border border-zinc-200 p-3 hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-900">
                        <div class="min-w-0">
                            <div class="truncate font-medium" dir="auto">{{ $occurrence->plan->title }}</div>
                            <div class="mt-1 text-xs text-zinc-500">
                                @if ($occurrence->scheduleRule?->timing_mode?->value === 'flexible_day')
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
                    <x-app.empty-state :title="__('planning_baseline.workspace.empty_today')" :description="__('planning_baseline.workspace.empty_today_help')" />
                @endforelse
            </div>
        </flux:card>

        <flux:card class="space-y-4">
            <div>
                <flux:heading size="lg">{{ __('planning_baseline.workspace.upcoming') }}</flux:heading>
                <flux:text>{{ __('planning_baseline.workspace.upcoming_help') }}</flux:text>
            </div>

            <div class="space-y-2">
                @forelse ($upcomingOccurrences as $occurrence)
                    <a href="{{ route('planner.show', $occurrence->plan) }}" class="block rounded-xl border border-zinc-200 p-3 hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-900">
                        <div class="font-medium" dir="auto">{{ $occurrence->plan->title }}</div>
                        <div class="mt-1 text-xs text-zinc-500">
                            <x-app.local-date :value="$occurrence->scheduled_start_at" :show-equivalent="false" />
                            @if ($occurrence->scheduleRule?->timing_mode?->value !== 'flexible_day')
                                · <x-app.local-time :value="$occurrence->scheduled_start_at" />
                            @endif
                        </div>
                    </a>
                @empty
                    <x-app.empty-state :title="__('planning_baseline.workspace.empty_upcoming')" />
                @endforelse
            </div>
        </flux:card>
    </div>
</section>
