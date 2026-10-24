@php
    $navigation = app(\App\Services\Surfaces\ExperienceNavigation::class)->for(auth()->user());
    $legacyItems = collect(app(\App\Services\Surfaces\UnifiedNavigation::class)->for(auth()->user()));
    $notificationsVisible = $legacyItems->contains(fn (array $item): bool => $item['key'] === 'notifications');
@endphp

@if (app(\App\Support\IntentionLauncher::class)->choicesFor(auth()->user()) !== [])
    <div class="mb-2 px-1">
        <flux:modal.trigger name="intention-launcher">
            <flux:button variant="primary" icon="plus" class="w-full justify-start" data-launcher-open>{{ __('launcher.button') }}</flux:button>
        </flux:modal.trigger>
    </div>
@endif

<flux:sidebar.item
    wire:navigate
    :href="route('dashboard')"
    :current="request()->routeIs('dashboard')"
    icon="home"
>
    {{ __('home.title') }}
</flux:sidebar.item>

@if($notificationsVisible)
    <livewire:notifications.nav-item :key="'publication-notifications-'.auth()->id()" />
@endif

@foreach($navigation['primary'] as $destination)
    <flux:sidebar.item
    wire:navigate
        :href="route($destination['route'])"
        :current="request()->routeIs(...$destination['patterns'])"
        :icon="$destination['icon']"
        data-nav-primary="{{ $destination['key'] }}"
    >
        {{ $destination['label'] }}
        @if ($destination['key'] === 'messages')
            <span x-data="messageBadge({ url: {{ \Illuminate\Support\Js::from(route('messages.unread')) }}, count: 0 })" x-show="count > 0" x-cloak x-text="count > 99 ? '99+' : count" class="ms-1 inline-flex min-w-5 items-center justify-center rounded-full bg-zinc-900 px-1.5 text-xs font-semibold text-white dark:bg-white dark:text-zinc-900" data-message-badge></span>
        @endif
    </flux:sidebar.item>
@endforeach

@if($navigation['spaces'] !== [])
    <div class="mt-4 border-t border-zinc-200 pt-3 dark:border-zinc-800" data-nav-spaces>
        <div class="px-3 pb-1 text-xs font-semibold uppercase tracking-wide text-zinc-500">
            {{ __('experience.navigation.spaces') }}
        </div>
        @foreach($navigation['spaces'] as $destination)
            <flux:sidebar.item
                wire:navigate
                :href="route($destination['route'])"
                :current="request()->routeIs(...$destination['patterns'])"
                :icon="$destination['icon']"
            >
                {{ $destination['label'] }}
            </flux:sidebar.item>
        @endforeach
    </div>
@endif

@if($navigation['account'] !== [])
    <div class="mt-4 border-t border-zinc-200 pt-3 dark:border-zinc-800">
        <div class="px-3 pb-1 text-xs font-semibold uppercase tracking-wide text-zinc-500">
            {{ __('experience.navigation.account') }}
        </div>
        @foreach($navigation['account'] as $item)
            <flux:sidebar.item
    wire:navigate
                :href="route($item['route'])"
                :current="request()->routeIs(...$item['patterns'])"
                :icon="$item['icon']"
            >
                {{ $item['label'] }}
            </flux:sidebar.item>
        @endforeach
    </div>
@endif

@if($navigation['help'] !== [])
    <div class="mt-3">
        <div class="px-3 pb-1 text-xs font-semibold uppercase tracking-wide text-zinc-500">
            {{ __('experience.navigation.help') }}
        </div>
        @foreach($navigation['help'] as $item)
            <flux:sidebar.item
    wire:navigate
                :href="route($item['route'])"
                :current="request()->routeIs(...$item['patterns'])"
                :icon="$item['icon']"
            >
                {{ $item['label'] }}
            </flux:sidebar.item>
        @endforeach
    </div>
@endif

@if($navigation['labs'] !== [])
    <div class="mt-3">
        <div class="px-3 pb-1 text-xs font-semibold uppercase tracking-wide text-zinc-500">
            {{ __('experience.navigation.labs') }}
        </div>
        @foreach($navigation['labs'] as $item)
            <flux:sidebar.item
    wire:navigate
                :href="route($item['route'])"
                :current="request()->routeIs(...$item['patterns'])"
                :icon="$item['icon']"
            >
                {{ $item['label'] }}
            </flux:sidebar.item>
        @endforeach
    </div>
@endif

@if($navigation['admin'] !== [])
    <div class="mt-4 border-t border-zinc-200 pt-3 dark:border-zinc-800">
        <div class="px-3 pb-1 text-xs font-semibold uppercase tracking-wide text-zinc-500">
            {{ __('experience.navigation.admin') }}
        </div>
        @foreach($navigation['admin'] as $item)
            <flux:sidebar.item
    wire:navigate
                :href="route($item['route'])"
                :current="request()->routeIs(...$item['patterns'])"
                :icon="$item['icon']"
            >
                {{ $item['label'] }}
            </flux:sidebar.item>
        @endforeach
    </div>
@endif
