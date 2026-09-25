<flux:sidebar.item
    :href="route('notifications.index')"
    :current="request()->routeIs('notifications.*')"
    icon="bell"
    wire:poll.30s
>
    <span>{{ __('notifications.title') }}</span>
    @if ($unreadCount > 0)
        <flux:badge size="sm">{{ $unreadCount }}</flux:badge>
    @endif
</flux:sidebar.item>
