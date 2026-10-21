<div>
<flux:card class="space-y-4" data-today-recent-activity>
        <div>
            <flux:heading size="lg">{{ __('home.recent_activity') }}</flux:heading>
            <flux:text>{{ __('home.recent_activity_help') }}</flux:text>
        </div>

        <div class="space-y-3">
            @forelse ($recentActivity->take(6) as $entry)
                <a href="{{ $entry->url }}" class="block rounded-xl border border-zinc-200 p-4 hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-900">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <flux:badge color="zinc">{{ $entry->kind }}</flux:badge>
                                <span class="font-semibold" dir="auto">{{ $entry->title }}</span>
                            </div>
                            @if ($entry->summary)
                                <div class="mt-2 text-sm text-zinc-500" dir="auto">{{ $entry->summary }}</div>
                            @endif
                        </div>
                        <div class="shrink-0 text-xs text-zinc-500">
                            <x-app.local-datetime :value="$entry->occurredAt" />
                        </div>
                    </div>
                </a>
            @empty
                <x-app.empty-state :title="__('home.no_recent_activity')" />
            @endforelse
                @if ($recentActivity->count() > 6)
                    <details class="group" data-today-overflow="recentActivity">
                        <summary class="cursor-pointer list-none text-sm font-medium text-zinc-600 hover:text-zinc-900 dark:text-zinc-400 dark:hover:text-zinc-100">
                            {{ __('home.schedule.more_items', ['count' => $recentActivity->count() - 6]) }}
                        </summary>
                        <ul class="mt-2 divide-y divide-zinc-100 text-sm dark:divide-zinc-800">
                            @foreach ($recentActivity->skip(6) as $overflowItem)
                                <li><a href="{{ $overflowItem->url }}" class="flex items-center gap-2 py-2 hover:underline"><span class="truncate" dir="auto">{{ $overflowItem->title }}</span></a></li>
                            @endforeach
                        </ul>
                    </details>
                @endif
        </div>
    </flux:card>
</div>
