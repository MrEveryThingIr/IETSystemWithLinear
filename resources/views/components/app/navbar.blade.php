@props(['title'])

@php
    $docsTopic = app(\App\Support\Docs\DocsHelpMap::class)->slugForRoute(request()->route()?->getName());
@endphp

<flux:header class="gap-1.5 !px-3 sm:gap-3 sm:!px-6 lg:!px-8 border-b border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
    <flux:sidebar.toggle class="lg:hidden" icon="bars-2" :aria-label="__('ui.navigation.open')" />
    <flux:text class="min-w-0 truncate font-medium text-zinc-900 dark:text-white">{{ $title }}</flux:text>
    <flux:spacer />
    @unless (config('release.profile') === 'planning_baseline')
        <flux:button :href="route('docs.show', $docsTopic)" variant="ghost" size="sm" icon="question-mark-circle">
            <span class="hidden sm:inline">{{ __('ui.navigation.help') }}</span>
        </flux:button>
    @endunless
    <x-app.locale-switcher />
    <x-app.user-menu />
</flux:header>
