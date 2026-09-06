<flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-950">
    <flux:sidebar.header>
        <flux:sidebar.brand :href="route('dashboard')" :name="config('app.name')" />
        <flux:sidebar.toggle class="lg:hidden" icon="x-mark" aria-label="Close navigation" />
    </flux:sidebar.header>
    <flux:sidebar.nav aria-label="Main navigation">
        <flux:sidebar.item :href="route('dashboard')" :current="request()->routeIs('dashboard')" icon="home">
            Dashboard
        </flux:sidebar.item>
    </flux:sidebar.nav>
</flux:sidebar>
