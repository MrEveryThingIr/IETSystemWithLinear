<li wire:key="today-occurrence-{{ $occurrence->uuid }}" data-today-row>
    <a
        href="{{ route('planner.show', $occurrence->plan).'#occurrence-'.$occurrence->uuid }}"
        class="flex items-center gap-3 rounded-md py-2 text-sm hover:bg-zinc-50 dark:hover:bg-zinc-900 {{ $nested ? 'px-1' : '' }}"
    >
        <span class="w-24 shrink-0 tabular-nums text-zinc-500">
            <x-app.local-time :value="$occurrence->scheduled_start_at" />–<x-app.local-time :value="$occurrence->scheduled_end_at" />
        </span>
        <span class="min-w-0 flex-1 truncate font-medium" dir="auto">{{ $occurrence->plan->title }}</span>
        <span class="shrink-0 text-xs text-zinc-500">{{ __('planner.occurrence_status.'.$occurrence->status->value) }}</span>
    </a>
</li>
