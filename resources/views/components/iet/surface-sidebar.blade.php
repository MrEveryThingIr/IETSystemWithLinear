@php
    $surfaceItems = app(\App\Services\Surfaces\UnifiedNavigation::class)->for(auth()->user());
    $rootOperator = \App\Support\PlatformAdmin::check(auth()->user());
@endphp

<flux:sidebar.item
    :href="route('dashboard')"
    :current="request()->routeIs('dashboard')"
    icon="home"
>
    {{ __('home.title') }}
</flux:sidebar.item>

@foreach($surfaceItems as $item)
    @if($item['key'] === 'notifications')
        <livewire:notifications.nav-item :key="'publication-notifications-'.auth()->id()" />
    @else
        <flux:sidebar.item
            :href="route($item['route'])"
            :current="request()->routeIs(...$item['patterns'])"
            :icon="$item['icon']"
        >
            {{ $item['label'] }}
        </flux:sidebar.item>
    @endif
@endforeach

@if($rootOperator)
    <flux:sidebar.item
        :href="route('platform.publication.index')"
        :current="request()->routeIs('platform.publication.*')"
        icon="adjustments-horizontal"
    >
        {{ app()->getLocale() === 'fa' ? 'انتشار قابلیت‌ها' : 'Publication control' }}
    </flux:sidebar.item>
@endif
