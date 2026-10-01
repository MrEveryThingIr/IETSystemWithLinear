<flux:sidebar
    sticky
    collapsible="mobile"
    class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-800 dark:bg-zinc-950"
>
    <flux:sidebar.header>
        <flux:sidebar.brand :href="route('dashboard')" :name="config('app.name')" />
        <flux:sidebar.toggle class="lg:hidden" icon="x-mark" :aria-label="__('ui.navigation.close')" />
    </flux:sidebar.header>

    <flux:sidebar.nav :aria-label="__('ui.navigation.main')">
        <x-iet.surface-sidebar />
    </flux:sidebar.nav>
</flux:sidebar>
