@php
    $navigation = app(\App\Services\Surfaces\ExperienceNavigation::class)->for(auth()->user());
    $legacyItems = collect(app(\App\Services\Surfaces\UnifiedNavigation::class)->for(auth()->user()));
    $notificationsVisible = $legacyItems->contains(fn (array $item): bool => $item['key'] === 'notifications');
@endphp

<flux:sidebar.item
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
    @if(count($destination['items']) === 1)
        @php($entry = $destination['items'][0])
        <flux:sidebar.item
            :href="route($entry['route'])"
            :current="request()->routeIs(...$entry['patterns'])"
            :icon="$destination['icon']"
        >
            {{ $destination['label'] }}
        </flux:sidebar.item>
    @else
        <details class="group/nav rounded-lg" @if(request()->routeIs(...$destination['patterns'])) open @endif>
            <summary class="flex cursor-pointer list-none items-center justify-between rounded-lg px-3 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-900">
                <span>{{ $destination['label'] }}</span>
                <span class="text-xs text-zinc-400 transition-transform group-open/nav:rotate-90">›</span>
            </summary>
            <div class="ms-3 mt-1 space-y-1 border-s border-zinc-200 ps-2 dark:border-zinc-800">
                @foreach($destination['items'] as $item)
                    <flux:sidebar.item
                        :href="route($item['route'])"
                        :current="request()->routeIs(...$item['patterns'])"
                        :icon="$item['icon']"
                    >
                        {{ $item['label'] }}
                    </flux:sidebar.item>
                @endforeach
            </div>
        </details>
    @endif
@endforeach

@if($navigation['account'] !== [])
    <div class="mt-4 border-t border-zinc-200 pt-3 dark:border-zinc-800">
        <div class="px-3 pb-1 text-xs font-semibold uppercase tracking-wide text-zinc-500">
            {{ __('experience.navigation.account') }}
        </div>
        @foreach($navigation['account'] as $item)
            <flux:sidebar.item
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
                :href="route($item['route'])"
                :current="request()->routeIs(...$item['patterns'])"
                :icon="$item['icon']"
            >
                {{ $item['label'] }}
            </flux:sidebar.item>
        @endforeach
    </div>
@endif
