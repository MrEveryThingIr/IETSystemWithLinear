@if (config('release.profile') === 'planning_baseline')
<flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
    <flux:sidebar.header>
        <flux:sidebar.brand :href="route('dashboard')" :name="config('app.name')" />
        <flux:sidebar.toggle class="lg:hidden" icon="x-mark" :aria-label="__('ui.navigation.close')" />
    </flux:sidebar.header>

    <flux:sidebar.nav :aria-label="__('ui.navigation.main')">
        <flux:sidebar.item :href="route('dashboard')" :current="request()->routeIs('dashboard')" icon="squares-2x2">
            {{ __('planning_baseline.navigation.workspace') }}
        </flux:sidebar.item>
        <flux:sidebar.item :href="route('planner.index')" :current="request()->routeIs('planner.*')" icon="calendar-days">
            {{ __('planning_baseline.navigation.planning') }}
        </flux:sidebar.item>
        <flux:sidebar.item :href="route('accounting.index')" :current="request()->routeIs('accounting.*') || request()->routeIs('exchange.*') || request()->routeIs('financial-obligations.*')" icon="banknotes">
            {{ __('planning_baseline.navigation.money') }}
        </flux:sidebar.item>
        <flux:sidebar.item :href="route('vault.index')" :current="request()->routeIs('vault.*')" icon="lock-closed">
            {{ __('planning_baseline.navigation.vault') }}
        </flux:sidebar.item>
        <flux:sidebar.item :href="route('profile.edit')" :current="request()->routeIs('profile.*')" icon="user-circle">
            {{ __('planning_baseline.navigation.identity') }}
        </flux:sidebar.item>
        <flux:sidebar.item :href="route('ai.chat')" :current="request()->routeIs('ai.*')" icon="sparkles">
            {{ __('ai.chat.title') }}
        </flux:sidebar.item>

        @if (request()->user()?->hasPlatformCapability(\App\PlatformCapability::ManageUsers))
            <flux:sidebar.item :href="route('platform.access-invitations')" :current="request()->routeIs('platform.access-invitations')" icon="user-plus">
                {{ __('planning_baseline.navigation.invitations') }}
            </flux:sidebar.item>
            <flux:sidebar.item :href="route('platform.access')" :current="request()->routeIs('platform.access')" icon="key">
                {{ __('planning_baseline.navigation.access') }}
            </flux:sidebar.item>
        @endif
    </flux:sidebar.nav>
</flux:sidebar>
@else
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
        <flux:sidebar.item :href="route('ai.chat')" :current="request()->routeIs('ai.*')" icon="sparkles">
            {{ __('ai.chat.title') }}
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
            <flux:sidebar.item :href="route('money.index')" :current="request()->routeIs('money.*') || request()->routeIs('financial-obligations.*')" icon="wallet">
                {{ __('accounting.baseline.title') }}
            </flux:sidebar.item>
            <flux:sidebar.item :href="route('accounting.index')" :current="request()->routeIs('accounting.*')" icon="banknotes">
                {{ __('accounting.title') }}
            </flux:sidebar.item>
            <flux:sidebar.item :href="route('exchange.index')" :current="request()->routeIs('exchange.*')" icon="arrows-right-left">
                {{ __('exchange.title') }}
            </flux:sidebar.item>
            <flux:sidebar.item :href="route('vault.index')" :current="request()->routeIs('vault.*')" icon="lock-closed">
                {{ __('vault.title') }}
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
            <flux:sidebar.item :href="route('system-map')" :current="request()->routeIs('system-map')" icon="map">
                {{ __('system_map.title') }}
            </flux:sidebar.item>
        @endunless
        @if (request()->user()?->hasPlatformCapability(\App\PlatformCapability::ManageUsers))
            <flux:sidebar.item :href="route('platform.access-invitations')" :current="request()->routeIs('platform.access-invitations')" icon="user-plus">
                {{ __('access.admin.title') }}
            </flux:sidebar.item>
        @endif
        @if (request()->user()?->hasPlatformCapability(\App\PlatformCapability::ViewPlatformAudit))
            <flux:sidebar.item :href="route('platform.development-origins')" :current="request()->routeIs('platform.development-origins')" icon="clock">
                {{ __('development.title') }}
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

@endif
