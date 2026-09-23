<flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-950">
    <flux:sidebar.header>
        <flux:sidebar.brand :href="route('dashboard')" :name="config('app.name')" />
        <flux:sidebar.toggle class="lg:hidden" icon="x-mark" :aria-label="__('ui.navigation.close')" />
    </flux:sidebar.header>
    <flux:sidebar.nav :aria-label="__('ui.navigation.main')">
        <flux:sidebar.item :href="route('dashboard')" :current="request()->routeIs('dashboard')" icon="home">
            {{ __('ui.navigation.dashboard') }}
        </flux:sidebar.item>
        <flux:sidebar.item :href="route('profile.edit')" :current="request()->routeIs('profile.*')" icon="user-circle">
            {{ __('ui.navigation.profile') }}
        </flux:sidebar.item>
        <flux:sidebar.item :href="route('groups.index')" :current="request()->routeIs('groups.*')" icon="users">
            {{ __('ui.navigation.groups') }}
        </flux:sidebar.item>
        <flux:sidebar.item :href="route('manual')" :current="request()->routeIs('manual') || request()->query('manual') === '1'" icon="book-open">
            {{ __('ui.navigation.manual') }}
        </flux:sidebar.item>
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
        @can('viewAny', App\Models\Actor::class)
            <flux:sidebar.item :href="route('actors.index')" :current="request()->routeIs('actors.*')" icon="users">
                {{ __('ui.navigation.actors') }}
            </flux:sidebar.item>
        @endcan
    </flux:sidebar.nav>
</flux:sidebar>
