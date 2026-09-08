<flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-950">
    <flux:sidebar.header>
        <flux:sidebar.brand :href="route('dashboard')" :name="config('app.name')" />
        <flux:sidebar.toggle class="lg:hidden" icon="x-mark" aria-label="Close navigation" />
    </flux:sidebar.header>
    <flux:sidebar.nav aria-label="Main navigation">
        <flux:sidebar.item :href="route('dashboard')" :current="request()->routeIs('dashboard')" icon="home">Dashboard</flux:sidebar.item>
        <flux:sidebar.item :href="route('groups.index')" :current="request()->routeIs('groups.*')" icon="users">Groups</flux:sidebar.item>
        @if (auth()->check() && app(\App\Actions\Administration\GlobalAccess::class)->can(auth()->user(), 'actors.manage'))
            <flux:sidebar.item :href="route('actors.index')" :current="request()->routeIs('actors.*')" icon="users">Actors</flux:sidebar.item>
        @endif
        @if (auth()->check() && app(\App\Actions\Administration\GlobalAccess::class)->can(auth()->user(), 'users.manage'))
            <flux:sidebar.item :href="route('users.index')" :current="request()->routeIs('users.*')" icon="user-group">Users</flux:sidebar.item>
        @endif
        @if (auth()->check() && app(\App\Actions\Administration\GlobalAccess::class)->can(auth()->user(), 'rbac.manage'))
            <flux:sidebar.item :href="route('administration.index')" :current="request()->routeIs('administration.*')" icon="cog-6-tooth">Administration</flux:sidebar.item>
        @endif
    </flux:sidebar.nav>
</flux:sidebar>
