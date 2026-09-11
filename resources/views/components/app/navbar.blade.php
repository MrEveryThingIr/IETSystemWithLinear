@props(['title'])

<flux:header class="gap-3 border-b border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
    <flux:sidebar.toggle class="lg:hidden" icon="bars-2" :aria-label="__('ui.navigation.open')" />
    <flux:text class="min-w-0 truncate font-medium text-zinc-900 dark:text-white">{{ $title }}</flux:text>
    <flux:spacer />
    <x-app.locale-switcher />
    <x-app.user-menu />
</flux:header>
