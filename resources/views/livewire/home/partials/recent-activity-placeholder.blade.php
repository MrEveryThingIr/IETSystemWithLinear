<div>
    <flux:card class="space-y-4" aria-busy="true">
        <div>
            <flux:heading size="lg">{{ __('home.recent_activity') }}</flux:heading>
            <flux:text>{{ __('home.recent_activity_help') }}</flux:text>
        </div>
        <div class="space-y-3" aria-hidden="true">
            @foreach (range(1, 3) as $row)
                <div class="h-16 animate-pulse rounded-xl bg-zinc-100 dark:bg-zinc-800"></div>
            @endforeach
        </div>
    </flux:card>
</div>
