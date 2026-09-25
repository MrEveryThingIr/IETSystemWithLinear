@php($officeAlpha = config('release.profile') === 'office_alpha')
<flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-950">
    <flux:sidebar.header>
        <flux:sidebar.brand :href="route('dashboard')" :name="config('app.name')" />
        <flux:sidebar.toggle class="lg:hidden" icon="x-mark" :aria-label="__('ui.navigation.close')" />
    </flux:sidebar.header>
    <flux:sidebar.nav :aria-label="__('ui.navigation.main')">
        <flux:sidebar.item :href="route('dashboard')" :current="request()->routeIs('dashboard')" icon="home">
            {{ __('home.title') }}
        </flux:sidebar.item>
        <livewire:notifications.nav-item />
        <flux:sidebar.item :href="route('intents.index')" :current="request()->routeIs('intents.*')" icon="magnifying-glass">
            {{ __('intents.directory.title') }}
        </flux:sidebar.item>
        <flux:sidebar.item :href="route('profile.edit')" :current="request()->routeIs('profile.*')" icon="user-circle">
            {{ __('ui.navigation.profile') }}
        </flux:sidebar.item>
        @unless ($officeAlpha)
            <flux:sidebar.item :href="route('journeys.index')" :current="request()->routeIs('journeys.*')" icon="map">
                {{ __('journeys.title') }}
            </flux:sidebar.item>
            <flux:sidebar.item :href="route('relationships.index')" :current="request()->routeIs('relationships.*')" icon="link">
                {{ __('relationships.title') }}
            </flux:sidebar.item>
            <flux:sidebar.item :href="route('proposals.index')" :current="request()->routeIs('proposals.*')" icon="document-text">
                {{ __('proposals.title') }}
            </flux:sidebar.item>
            <flux:sidebar.item :href="route('contracts.index')" :current="request()->routeIs('contracts.*')" icon="document-check">
                {{ __('contracts.title') }}
            </flux:sidebar.item>
            <flux:sidebar.item :href="route('planner.index')" :current="request()->routeIs('planner.*')" icon="calendar-days">
                {{ __('planner.title') }}
            </flux:sidebar.item>
            <flux:sidebar.item :href="route('accounting.index')" :current="request()->routeIs('accounting.*')" icon="banknotes">
                {{ __('accounting.title') }}
            </flux:sidebar.item>
            <flux:sidebar.item :href="route('content.library')" :current="request()->routeIs('content.library')" icon="rectangle-stack">
                {{ __('library.title') }}
            </flux:sidebar.item>
            <flux:sidebar.item :href="route('groups.index')" :current="request()->routeIs('groups.*')" icon="users">
                {{ __('ui.navigation.groups') }}
            </flux:sidebar.item>
            <flux:sidebar.item :href="route('manual')" :current="request()->routeIs('manual') || request()->query('manual') === '1'" icon="book-open">
                {{ __('ui.navigation.manual') }}
            </flux:sidebar.item>
        @endunless
        @if (request()->user()?->hasPlatformCapability(\App\PlatformCapability::ManageUsers))
            <flux:sidebar.item :href="route('platform.access-invitations')" :current="request()->routeIs('platform.access-invitations')" icon="user-plus">
                {{ __('access.admin.title') }}
            </flux:sidebar.item>
        @endif
        @unless ($officeAlpha)
            @can('viewAny', App\Models\Actor::class)
                <flux:sidebar.item :href="route('actors.index')" :current="request()->routeIs('actors.*')" icon="users">
                    {{ __('ui.navigation.actors') }}
                </flux:sidebar.item>
            @endcan
        @endunless
    </flux:sidebar.nav>
</flux:sidebar>
